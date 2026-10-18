#!/usr/bin/env bash
# The questions of `opensim setup`, asked in the terminal. The setup decides what to ask, in which order, and what to do of the
# answers (opensim setup --frontend DIR: see src/Ui/ScriptUi.php for the lines it tells and the answers it takes). This script
# only shows a question and brings the answer back, with dialog when there is one and a terminal to draw on, else with plain
# prompts.
#
#   source libexec/setup-frontend.sh
#   frontend_run opensim setup        # asks what the setup asks, returns its status
#
# Environment:
#   OPENSIM_SETUP_UI=plain|dialog   the way to ask (default: dialog when it is there, plain otherwise)
#   FE_TTY=file                     where the person types (default /dev/tty)
#   FE_INPUT_FD=n                   a descriptor to read the answers from instead (the tests)
#   FE_SCREEN=file                  where dialog draws and reads the keys (default /dev/tty)

FE_SEP=$'\x1f'
FE_TTY=${FE_TTY:-/dev/tty}
FE_INPUT_FD=${FE_INPUT_FD:-}
FE_SCREEN=${FE_SCREEN:-/dev/tty}   # where dialog draws and reads the keys
FE_UI=
FE_PENDING=      # what was told since the last question, shown above the next one (dialog)
FE_ANSWER=       # the answer to the last question
FE_TITLE='OpenSim setup'
FE_BUSY=

# --- the lines -------------------------------------------------------------------------------------------------------------

# Write out the characters that cannot be in a field (the separator, tabs, new lines, backslashes)
fe_escape() {
    local s=$1
    s=${s//\\/\\\\}
    s=${s//$'\n'/\\n}
    s=${s//$'\t'/\\t}
    s=${s//$'\r'/\\r}
    s=${s//"$FE_SEP"/\\s}
    printf '%s' "$s"
}

# What fe_escape wrote, in FE_UNESCAPED (no subshell: it is called for every field)
fe_unescape() {
    local s=$1 out= c
    if [[ $s != *\\* ]]; then
        FE_UNESCAPED=$s
        return
    fi
    while [[ -n $s ]]; do
        if [[ ${s:0:1} == '\' && -n ${s:1:1} ]]; then
            c=${s:1:1}
            case $c in
            n) out+=$'\n' ;;
            t) out+=$'\t' ;;
            r) out+=$'\r' ;;
            s) out+=$FE_SEP ;;
            *) out+=$c ;;
            esac
            s=${s:2}
        else
            out+=${s:0:1}
            s=${s:1}
        fi
    done
    FE_UNESCAPED=$out
}

# Split a line of the setup into FE_FIELDS, unescaped
fe_split() {
    local -a raw
    local i
    IFS=$FE_SEP read -r -a raw <<<"$1" || true
    FE_FIELDS=()
    for ((i = 0; i < ${#raw[@]}; i++)); do
        fe_unescape "${raw[i]}"
        FE_FIELDS[i]=$FE_UNESCAPED
    done
}

# --- what is told ----------------------------------------------------------------------------------------------------------

fe_color() { # code text
    if [[ -t 1 ]]; then printf '\e[%sm%s\e[0m' "$1" "$2"; else printf '%s' "$2"; fi
}

fe_tell() { # level text
    local text=$2 line
    case $1 in
    intro)
        FE_TITLE=$text
        [[ $FE_UI == dialog ]] || printf '\n%s\n' "$(fe_color 1 "== $text ==")"
        ;;
    note)
        if [[ $FE_UI == dialog ]]; then
            FE_PENDING+=$text$'\n\n'
        else
            while IFS= read -r line; do printf '  %s\n' "$line"; done <<<"$text"
        fi
        ;;
    warn)
        if [[ $FE_UI == dialog ]]; then FE_PENDING+="! $text"$'\n\n'; else printf '%s\n' "$(fe_color 33 "! $text")"; fi
        ;;
    error)
        if [[ $FE_UI == dialog ]]; then FE_PENDING+="ERROR: $text"$'\n\n'; else printf '%s\n' "$(fe_color 31 "x $text")" >&2; fi
        ;;
    outro)
        fe_flush
        printf '\n%s\n' "$text"
        ;;
    out) printf '    %s\n' "$text" ;;
    busy)
        FE_BUSY=1
        printf '%s\n' "$(fe_color 2 "... $text")"
        ;;
    esac
}

# What is waiting to be told, when no question comes to carry it
fe_flush() {
    [[ -n $FE_PENDING ]] && printf '%s\n' "${FE_PENDING%$'\n\n'}"
    FE_PENDING=
}

# --- the questions, plain ---------------------------------------------------------------------------------------------------

# Read from the person: the options of read and the name of the variable
fe_in() {
    if [[ -n $FE_INPUT_FD ]]; then
        IFS= read -r -u "$FE_INPUT_FD" "$@"
    else
        IFS= read -r "$@" <"$FE_TTY"
    fi
}

# Read a line from the person: 0 when read, 2 at the end of the input (nobody is there to answer)
fe_read() { # prompt default [secret]
    local value
    if [[ -n ${3:-} ]]; then
        fe_in -s -p "$1" value || return 2
        printf '\n' >&2
    elif ((BASH_VERSINFO[0] >= 4)) && [[ $FE_TTY == /dev/tty && -z $FE_INPUT_FD ]]; then
        fe_in -e -p "$1" -i "$2" value || return 2
    else
        fe_in -p "$1" value || return 2
        [[ -z $value ]] && value=$2
    fi
    FE_ANSWER=$value
}

# The keys and labels of the options of a question, in FE_KEYS and FE_LABELS
fe_options() { # fields from the 7th on
    local i
    FE_KEYS=()
    FE_LABELS=()
    for ((i = 7; i + 1 < ${#FE_FIELDS[@]}; i += 2)); do
        FE_KEYS+=("${FE_FIELDS[i]}")
        FE_LABELS+=("${FE_FIELDS[i + 1]}")
    done
    # The last label can be the empty string, which the split dropped
    if (((${#FE_FIELDS[@]} - 7) % 2 == 1)); then
        FE_KEYS+=("${FE_FIELDS[${#FE_FIELDS[@]} - 1]}")
        FE_LABELS+=("")
    fi
}

# Answers that are not an answer: :back and :quit
fe_special() {
    case $FE_ANSWER in
    :back | :b) return 1 ;;
    :quit | :q) return 2 ;;
    esac
    return 0
}

# Each asks FE_FIELDS (id kind label default hint error options...) and leaves FE_ANSWER; 0 answered, 1 back, 2 quit
fe_plain_text() {
    local label=${FE_FIELDS[3]} default=${FE_FIELDS[4]:-} hint=${FE_FIELDS[5]:-} error=${FE_FIELDS[6]:-}
    [[ -n $error ]] && fe_tell warn "$error"
    [[ -n $hint ]] && printf '  %s\n' "$hint"
    fe_read "$label${default:+ [$default]}: " "$default" || return 2
    fe_special
}

fe_plain_secret() {
    local label=${FE_FIELDS[3]} hint=${FE_FIELDS[5]:-} error=${FE_FIELDS[6]:-}
    [[ -n $error ]] && fe_tell warn "$error"
    [[ -n $hint ]] && printf '  %s\n' "$hint"
    fe_read "$label: " '' secret || return 2
}

fe_plain_confirm() {
    local label=${FE_FIELDS[3]} default=${FE_FIELDS[4]:-yes} choice='Y/n' answer
    [[ $default == no ]] && choice='y/N'
    while true; do
        fe_in -p "$label ($choice, :back, :quit) " answer || return 2
        case $answer in
        '') FE_ANSWER=$default ;;
        [Yy] | [Yy][Ee][Ss]) FE_ANSWER=yes ;;
        [Nn] | [Nn][Oo]) FE_ANSWER=no ;;
        *)
            FE_ANSWER=$answer
            fe_special && continue
            return $?
            ;;
        esac
        return 0
    done
}

fe_plain_choose() {
    local label=${FE_FIELDS[3]} default=${FE_FIELDS[4]:-} hint=${FE_FIELDS[5]:-} i choice number=1
    fe_options
    [[ -n $hint ]] && printf '  %s\n' "$hint"
    printf '%s\n' "$label"
    for ((i = 0; i < ${#FE_KEYS[@]}; i++)); do
        [[ ${FE_KEYS[i]} == "$default" ]] && number=$((i + 1))
        printf '  %d) %s\n' "$((i + 1))" "${FE_LABELS[i]}"
    done
    while true; do
        fe_in -p "Number [$number]: " choice || return 2
        FE_ANSWER=$choice
        fe_special || return $?
        [[ -z $choice ]] && choice=$number
        if [[ $choice =~ ^[0-9]+$ ]] && ((choice >= 1 && choice <= ${#FE_KEYS[@]})); then
            FE_ANSWER=${FE_KEYS[choice - 1]}
            return 0
        fi
        # The key itself is an answer too
        for ((i = 0; i < ${#FE_KEYS[@]}; i++)); do
            if [[ ${FE_KEYS[i]} == "$choice" ]]; then
                FE_ANSWER=$choice
                return 0
            fi
        done
    done
}

fe_plain_checklist() {
    local label=${FE_FIELDS[3]} default=${FE_FIELDS[4]:-} hint=${FE_FIELDS[5]:-} i n answer numbers=
    local -a checked=()
    fe_options
    [[ -n $hint ]] && printf '  %s\n' "$hint"
    printf '%s\n' "$label"
    for ((i = 0; i < ${#FE_KEYS[@]}; i++)); do
        local mark=' '
        [[ ",$default," == *",${FE_KEYS[i]},"* ]] && mark=x && numbers+="${numbers:+,}$((i + 1))"
        printf '  %d) [%s] %s\n' "$((i + 1))" "$mark" "${FE_LABELS[i]}"
    done
    while true; do
        fe_in -p "Numbers, as [$numbers] ('-' for none): " answer || return 2
        FE_ANSWER=$answer
        fe_special || return $?
        [[ -z $answer ]] && answer=$numbers
        [[ $answer == - ]] && answer=
        checked=()
        for n in ${answer//,/ }; do
            if [[ $n =~ ^[0-9]+$ ]] && ((n >= 1 && n <= ${#FE_KEYS[@]})); then
                checked+=("${FE_KEYS[n - 1]}")
            fi
        done
        FE_ANSWER=$(
            IFS=,
            printf '%s' "${checked[*]:-}"
        )
        return 0
    done
}

# --- the questions, with dialog ---------------------------------------------------------------------------------------------

# Run dialog on the terminal of the person, its answer in FE_ANSWER: 0 answered, 1 back (Cancel), 2 quit (Escape)
fe_dialog() { # arguments of dialog
    local out status=0
    out=$(dialog --output-fd 9 --title "$FE_TITLE" --cancel-label Back "$@" 9>&1 >"$FE_SCREEN" <"$FE_SCREEN") || status=$?
    FE_ANSWER=$out
    case $status in
    0) return 0 ;;
    1) return 1 ;;
    *) return 2 ;;
    esac
}

# What was told since the last question, and the error of this one, above the question
fe_dialog_text() { # label
    local error=${FE_FIELDS[6]:-} text=$FE_PENDING
    FE_PENDING=
    [[ -n $error ]] && text+="! $error"$'\n\n'
    printf '%s' "$text$1"
}

fe_dialog_ask() {
    local kind=${FE_FIELDS[2]} label=${FE_FIELDS[3]} default=${FE_FIELDS[4]:-} hint=${FE_FIELDS[5]:-} i text status=0
    [[ -n $hint ]] && label+=$'\n'"($hint)"
    text=$(fe_dialog_text "$label")
    case $kind in
    text) fe_dialog --inputbox "$text" 0 0 "$default" ;;
    secret) fe_dialog --insecure --passwordbox "$text" 0 0 ;;
    confirm)
        # Yes answers 0, No answers 1: the Back button is the one with the label
        local -a no=(--no-label No)
        [[ $default == no ]] && no+=(--defaultno)
        dialog --title "$FE_TITLE" --yes-label Yes "${no[@]}" --extra-button --extra-label Back --yesno "$text" 0 0 <"$FE_SCREEN" >"$FE_SCREEN" || status=$?
        case $status in
        0) FE_ANSWER=yes ;;
        1) FE_ANSWER=no ;;
        3) return 1 ;;
        *) return 2 ;;
        esac
        ;;
    choose)
        fe_options
        local -a items=()
        for ((i = 0; i < ${#FE_KEYS[@]}; i++)); do items+=("${FE_KEYS[i]}" "${FE_LABELS[i]}"); done
        fe_dialog --default-item "$default" --menu "$text" 0 0 0 "${items[@]}"
        ;;
    checklist)
        fe_options
        local -a boxes=()
        for ((i = 0; i < ${#FE_KEYS[@]}; i++)); do
            local state=off
            [[ ",$default," == *",${FE_KEYS[i]},"* ]] && state=on
            boxes+=("${FE_KEYS[i]}" "${FE_LABELS[i]}" "$state")
        done
        fe_dialog --separate-output --checklist "$text" 0 0 0 "${boxes[@]}" || status=$?
        FE_ANSWER=${FE_ANSWER//$'\n'/,}
        return $status
        ;;
    esac
}

# --- the dialogue ----------------------------------------------------------------------------------------------------------

# The way to ask: dialog when it is there and the person has a terminal, else plain prompts
fe_choose_ui() {
    FE_UI=${OPENSIM_SETUP_UI:-}
    [[ -n $FE_UI ]] && return
    FE_UI=plain
    if command -v dialog >/dev/null 2>&1 && [[ $FE_TTY == /dev/tty && $FE_SCREEN == /dev/tty && -t 1 && ${TERM:-dumb} != dumb ]]; then
        FE_UI=dialog
    fi
}

# Answer a question of the setup (FE_FIELDS: ask id kind label default hint error options...)
fe_answer() {
    local id=${FE_FIELDS[1]} kind=${FE_FIELDS[2]} status=0
    FE_ANSWER=
    if [[ $FE_UI == dialog ]]; then
        fe_dialog_ask || status=$?
    else
        FE_PENDING=
        "fe_plain_$kind" || status=$?
    fi
    case $status in
    0) printf 'answer%s%s%s%s\n' "$FE_SEP" "$id" "$FE_SEP" "$(fe_escape "$FE_ANSWER")" >&6 ;;
    1) printf 'back%s%s\n' "$FE_SEP" "$id" >&6 ;;
    *) printf 'quit%s%s\n' "$FE_SEP" "$id" >&6 ;;
    esac
}

# Run the setup (the command and its arguments) with its questions asked here; the status of the setup
frontend_run() {
    local dir line partial= command_status=0 status=0 setup_pid
    dir=$(mktemp -d "${TMPDIR:-/tmp}/opensim-setup.XXXXXX") || return 1
    mkfifo "$dir/questions" "$dir/answers" || {
        rm -rf "$dir"
        return 1
    }
    fe_choose_ui

    "$@" --frontend "$dir" &
    setup_pid=$!
    # Leaving the script leaves the setup: it ends with its next question
    trap 'kill "$setup_pid" 2>/dev/null' INT TERM
    # Open for reading and writing, which does not wait for the setup (it may not even start); the ends the setup opens are the
    # other way round
    exec 5<>"$dir/questions" 6<>"$dir/answers"

    while true; do
        if IFS= read -r -t 1 line <&5; then
            line=$partial$line
            partial=
        else
            # Nothing for a second (the status of a timeout is not the same in every bash): the setup is thinking, or gone. The pipe
            # cannot end by itself, this script holds both its ends
            partial+=$line
            kill -0 "$setup_pid" 2>/dev/null && continue
            break
        fi
        fe_split "$line"
        case ${FE_FIELDS[0]} in
        hello) ;;
        intro | note | warn | error | outro | out | busy) fe_tell "${FE_FIELDS[0]}" "${FE_FIELDS[1]:-}" ;;
        ready) FE_BUSY= ;;
        ask) fe_answer ;;
        bye)
            status=${FE_FIELDS[1]:-0}
            break
            ;;
        esac
    done
    fe_flush

    exec 5<&- 6>&-
    wait "$setup_pid" 2>/dev/null || command_status=$?
    trap - INT TERM
    rm -rf "$dir"
    # The status the setup said, else the one it ended with (it was killed, it never said)
    ((status == 0)) && status=$command_status
    return "$status"
}
