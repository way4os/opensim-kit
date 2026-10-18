#!/usr/bin/env bash

# Utility to move an existing OpenSimulator instance from a remote server
# - run the script from the new host
# - run the script as regular user (the one using opensim)
# - local user must have ssh access to remote server
# - local user must have sudo rights
# - remote server must allow mysql connection from local machine
# - mysql host is set to localhost.
#####################################################################
# DO NOT RUN IF NEW HOST IS ALREADY MYSQL HOST FOR REMOTE SIMULATOR #
#####################################################################
# - some parameters in $EtcRoot/opensim.conf will replace the imported ones:
#     BinDirectory
#     EstateConnectionString
#     GLBSpecificConnectionString
#     GridCommon

# Copyright 2015 Olivier van Helden <olivier@van-helden.net>
# Released under GNU Affero GPL v3.0 license
#    http://www.gnu.org/licenses/agpl-3.0.html

BASEDIR=$(dirname $(dirname $(realpath "$0")))
. $BASEDIR/lib/os-helpers || exit 1
BINDIR=$BASEDIR/bin

[ "$OpenSimExe" ] || exit 1
[ "$EtcRoot" ] || exit 1
DEBUG=yes

trap 'rm -f $TMP*; rm -rf $TMPDIR' EXIT
TMPDIR=$(mktemp -d -p $BASEDIR/var || end $?)
TMP=$TMPDIR/$PGM.$$

debug TMPDIR $TMPDIR
debug TMP $TMP

[ "$2" ] || end 1 "usage $PGM <source host> <simulator 1> [<simulator 2] [...]"
debug BASEDIR $BASEDIR
debug EtcRoot $EtcRoot
debug BINDIR $BINDIR
debug BinDirectory $BinDirectory
debug OpenSimExe $OpenSimExe
lr=$(printf "\n\b")
clr="\33[2K\r"
source=$1
shift

echo "$source" | egrep -q "[[:alnum:]-]\.[[:alnum:]-]" || end $? "$source: need a fully qualified host name"
check=$(ping -c1 $source 2>&1 >/dev/null) || end $? "${check/ping: /}"

debug get local config from $EtcRoot
debug BinDirectory $BinDirectory
debug Include-Common ${Include_Common}

for simulator in $@; do
	debug transfering simulator $simulator from $source
	mkdir $TMPDIR/$simulator && cd $TMPDIR/$simulator || end $?

	debug get remote config file $source:$EtcRoot/opensim.d/$simulator.ini
	scp $source:$EtcRoot/opensim.d/$simulator.ini $TMP.ini || end $?
	cleanupIni $TMP.ini >$simulator.remote.ini

	debug get remote db config
	crudget $simulator.remote.ini DatabaseService ConnectionString |
		tr ";" "\n" | grep = | grep -v "Old Guids" |
		sed -e "s/^Data Source/REMOTEDBHOST/" -e "s/^Database/REMOTEDBNAME/" -e "s/^User ID/REMOTEDBUSER/" -e "s/^Password/REMOTEDBPASS/" |
		tee $TMP.remotedb.ini && . $TMP.remotedb.ini || end $?

	if [ -e $EtcRoot/opensim.d/$simulator.ini ]; then
		log 1 "$EtcRoot/opensim.d/$simulator.ini already exists"
		if ! yesno "Replace with remote config?"; then
			yesno "Erase other data and replace with remote content?" || end $?
			cp $EtcRoot/opensim.d/$simulator.ini $TMP.ini
		fi
	fi
	cleanupIni $TMP.ini >$simulator.ini

	debug get local db config
	crudget $simulator.ini DatabaseService ConnectionString |
		tr ";" "\n" | grep = | grep -v "Old Guids" |
		sed -e "s/^Data Source/DBHOST/" -e "s/^Database/DBNAME/" -e "s/^User ID/DBUSER/" -e "s/^Password/DBPASS/" |
		tee $simulator.$$.db.ini && . $simulator.$$.db.ini || end $?

	[ "$BinDirectory" ] && crudini --set $simulator.ini Launch BinDir "$BinDirectory"
	[ "$EstateConnectionString" ] && crudini --set $simulator.ini DatabaseService EstateConnectionString "\"$EstateConnectionString\""
	[ "$GLBSpecificConnectionString" ] && crudini --set $simulator.ini Gloebit GLBSpecificConnectionString "\"$GLBSpecificConnectionString\""
	[ "$GridCommon" ] && crudini --set $simulator.ini Includes Include-Common "$GridCommon"

	SimName=$(crudget $simulator.ini Launch SimName)
	# just make sure SimName is lowercase and without spaces
	$SimName=$(echo $SimName | sed "s/[^[:alnum:]_]//g" | tr [:upper:] [:lower:])

	## Forget that stuff, simname **is** the MachineName
	# log check if MachineName is present and valid
	# MachineName==$(crudget $simulator.ini Launch MachineName)
	# [ "$MachineName" ] \
	# && MachineName=$(echo $MachineName | sed "s/[^[:alnum:]_]//g" ) \
	# || MachineName=$(echo $SimName | sed "s/[^[:alnum:]_]//g" | tr [:upper:] [:lower:]) \
	# && [ "$MachineName" ] \
	# && crudini --set $simulator.ini Launch MachineName $MachineName \
	# || end $? MachineName $MachineName empty
	# log MachineName $MachineName

	# log rough and dirty LogConfig fix
	crudini --set $simulator.ini Launch LogConfig "$(crudget $GridCommon Const DataRoot | sed -e "s#\${Launch|SimName}#${SimName}#g" -e "s#\${Launch|MachineName}#${SimName}#g")/config/log.config"
	# crudini --set $simulator.ini Launch LogConfig "$(crudget $GridCommon Const DataRoot | sed -e "s#\${Launch|SimName}#${MachineName}#g" -e "s#\${Launch|MachineName}#${MachineName}#g")/config/log.config"

	debug "Creating user $DBUSER (if not exists)"
	echo "CREATE USER IF NOT EXISTS $DBUSER IDENTIFIED BY '$DBPASS'" | sudo mysql -BN
	if [ "$(echo "SHOW DATABASES LIKE '$DBNAME'" | sudo mysql -BN)" ]; then
		log 1 Database $DBNAME already exists
		if ! yesno "Erase all data and replace with remote content?"; then
			read -p "Enter a new database name or press Ctrl-C to cancel: " DBNAME
			if [ "$(echo "SHOW DATABASES LIKE '$DBNAME'" | sudo mysql -BN)" ]; then
				end $? second attemp not better, db $DBNAME also exists
			fi
		fi
	fi
	echo "CREATE DATABASE IF NOT EXISTS $DBNAME;
  GRANT ALL ON $DBNAME.* TO $DBUSER;" | sudo mysql -BN
	[ "$(echo "SHOW DATABASES like '$DBNAME'" | sudo mysql -BN)" ] && debug Database $DBNAME created || end 1 "Could not create db $DBNAME"

	echo "CREATE TABLE test_$PGM_$$ (t integer); DROP TABLE test_$PGM_$$;" | mysql -u$DBUSER -p"$DBPASS" $DBNAME ||
		end $? error accessing local database
	# log test local db connection
	# log test remote db connection

	# create local directories and sync non cache ones
	# section=
	# egrep -v "http:|https:|^Include|OutboundDisallowForUserScripts" $simulator.live.ini \
	# | inigrep / | sed "s/ *=.*//" | while read line
	# do
	#   echo $line | grep -q "\[" && section=$(echo $line | sed "s/[^[:alpha:]]//g") && continue
	#   echo "$section $line"
	# done | sort -u

	debug get remote live config
	ssh $source "screen -x $simulator -X stuff 'config save /tmp/$simulator.live.$$.ini$lr'" || end $? "Is remote simulator up and running?"
	scp $source:/tmp/$simulator.live.$$.ini ./$simulator.live.ini || end $? "Could not get live config. Is remote simulator up and running?"

	log 1 stop remote simulator
	ssh $source "$BINDIR/opensim stop now $simulator" || end $?
	ssh -t $source "screen -x $simulator"

	debug "dump remote db to local one"
	debug ssh $source "$BINDIR/opensim stop $simulator"
	DUMPSIZE=$(mysql --skip-column-names -h$source -u$REMOTEDBUSER -p"$REMOTEDBPASS" $REMOTEDBNAME <<<"
  SELECT ROUND(SUM(data_length + index_length) * 0.5, 0)
  FROM information_schema.TABLES WHERE table_schema='$REMOTEDBNAME';
  ")
	mysqldump -h$source -u$REMOTEDBUSER -p"$REMOTEDBPASS" --hex-blob $REMOTEDBNAME |
		pv -pbae --size ${DUMPSIZE} |
		mysql -u$DBUSER -p"$DBPASS" $DBNAME ||
		end $?

	debug calculating local config log $GridCommon to grid.ini
	cp $simulator.ini $simulator.local.ini
	cleanupIni $GridCommon >$TMP.ini
	crudini --merge $simulator.local.ini <$TMP.ini
	cat $simulator.local.ini | tr "$ :/\"" "\n" | grep '{.*|.*}' | sort -u | tr "{|}" " " |
		while read section variable; do
			value=$(crudget $simulator.local.ini $section $variable)
			# echo "  $section->$variable = $value"
			sed -i~ "s#\${$section|$variable}#$value#g" $simulator.local.ini || end $?
		done

	echo "AssetCache CacheRoot
Const CacheRoot
Const DataRoot
Const LogsRoot
Const ConfigDirectory
DataSnapshot snapshot_cache_directory
GridService MapTileDirectory
Startup regionload_regionsdir" | while read section param; do
		dst=$(crudget $simulator.local.ini $section $param)
		[ "$dst" ] && mkdir -p "$dst"

		src=$(crudget $simulator.live.ini $section $param)
		[ "$src" ] || continue
		echo $section $param $src $dst | egrep -qi "cache" && continue # exclude cache
		echo "/$src/" | egrep -qi "/$simulator/" || continue                 # exclude shared
		debug sync $source:$src/ to $dst/
		rsync --progress -Waz $source:"$src"/ "$dst"/ || end $?
	done
	regionload_regionsdir=$(crudget $simulator.local.ini Startup regionload_regionsdir)
	[ "$regionload_regionsdir" ] || end $? regionload_regionsdir not set
	find $regionload_regionsdir -name "*.ini" | grep -q . || end $? "no region found in $regionload_regionsdir"

	debug "activate $EtcRoot/opensim.d/$simulator.ini"
	mkdir -p $EtcRoot/opensim.d
	cp $simulator.ini $EtcRoot/opensim.d/$simulator.ini

	debug starting local simulator $simulator
	opensim start $simulator || end $?

	debug "deactivate $simulator on source"
	ssh $source "mv $EtcRoot/opensim.d/$simulator.ini $EtcRoot/opensim.d/$simulator.transfered" || end $?
	debug "We should be good now"
done

[ $errors ] && end errors $errors
# ssh herbie $BINDIR/opensim status
