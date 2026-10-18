#!/usr/bin/env bash

# The script that asks the questions of the setup (libexec/setup-frontend.sh), against the real side of the engine
# (tests/Unit/script-driver.php asks through ScriptUi). The person is a file of answers; dialog is a fake that answers from a file too.
#
# Run with: tests/lib/bashunit tests/Unit (or vendor/bin/pest)

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
DRIVER="$ROOT/tests/Unit/script-driver.php"

function set_up() {
	WORK=$(mktemp -d)
	export RESULT="$WORK/result.json"
}

function tear_down() {
	rm -rf "$WORK"
}

# Run the front-end in a shell as strict as setup.sh, with the answers of the person written one per line in $1
# (the scenario in $2), what it printed in $WORK/out and its status in $WORK/status
run_frontend() {
	printf '%s\n' "$1" >"$WORK/answers.txt"
	(
		set -euo pipefail
		source "$ROOT/libexec/setup-frontend.sh"
		exec 7<"$WORK/answers.txt"
		export FE_INPUT_FD=7 FE_TTY="$WORK/none" OPENSIM_SETUP_UI=plain
		status=0
		frontend_run php "$DRIVER" "${2:-questions}" >"$WORK/out" 2>"$WORK/err" || status=$?
		echo "$status" >"$WORK/status"
	)
}

function test_escape_and_unescape_keep_what_the_protocol_cannot_hold() {
	(
		source "$ROOT/libexec/setup-frontend.sh"
		text=$'a\tb\nc\\d'"${FE_SEP}"$'e\r'
		escaped=$(fe_escape "$text")
		fe_unescape "$escaped"
		[ "$FE_UNESCAPED" = "$text" ] && [[ $escaped != *$'\t'* && $escaped != *$'\n'* && $escaped != *"$FE_SEP"* ]]
	)
	assert_successful_code
}

function test_a_line_is_split_with_the_empty_fields_it_has() {
	source "$ROOT/libexec/setup-frontend.sh"
	fe_split "ask${FE_SEP}1${FE_SEP}text${FE_SEP}Name${FE_SEP}${FE_SEP}a hint${FE_SEP}"
	assert_equals "ask|1|text|Name||a hint" "$(IFS='|'; echo "${FE_FIELDS[*]}")"
}

function test_an_answer_that_is_refused_is_asked_again_with_the_message() {
	run_frontend "Ad
Ada
secret
y
1
" questions

	assert_equals "0" "$(cat "$WORK/status")"
	assert_contains "! Too short." "$(cat "$WORK/out")"
	assert_equals '{"name":"Ada","password":"secret","sure":true,"which":"a","some":["x"]}' "$(php -r 'echo json_encode(json_decode(file_get_contents($argv[1])));' "$RESULT")"
}

function test_the_questions_with_valid_answers() {
	run_frontend "Ada Lovelace
p@ss\\word
n
2
1,3" questions

	assert_equals "0" "$(cat "$WORK/status")"
	assert_equals '{"name":"Ada Lovelace","password":"p@ss\\word","sure":false,"which":"b","some":["x","z"]}' "$(php -r 'echo json_encode(json_decode(file_get_contents($argv[1])));' "$RESULT")"
	local out
	out=$(cat "$WORK/out")
	assert_contains "== Test setup ==" "$out"
	assert_contains "Line one" "$out"
	assert_contains "! A warning" "$out"
	assert_contains "working on it" "$out"
	assert_contains "All done" "$out"
}

function test_the_defaults_are_the_answers_of_an_empty_line() {
	run_frontend $'\n\n\n\n' questions

	# Name Ada (the default), a secret with nothing in it, yes, a, and x (checked at first)
	assert_equals '{"name":"Ada","password":"","sure":true,"which":"a","some":["x"]}' "$(php -r 'echo json_encode(json_decode(file_get_contents($argv[1])));' "$RESULT")"
}

function test_back_and_quit_are_told_to_the_setup() {
	run_frontend ":back
:quit" back

	assert_equals "0" "$(cat "$WORK/status")"
	assert_equals '{"back":true,"quit":true}' "$(php -r 'echo json_encode(json_decode(file_get_contents($argv[1])));' "$RESULT")"
}

function test_the_status_of_the_setup_is_the_one_of_the_script() {
	run_frontend "" fail
	assert_equals "3" "$(cat "$WORK/status")"
	assert_contains "It failed" "$(cat "$WORK/err")"
}

function test_a_setup_that_does_not_start_ends_the_script() {
	(
		set -euo pipefail
		source "$ROOT/libexec/setup-frontend.sh"
		export FE_TTY="$WORK/none" OPENSIM_SETUP_UI=plain
		status=0
		frontend_run /nonexistent/setup >/dev/null 2>&1 || status=$?
		echo "$status" >"$WORK/status"
	)
	assert_not_equals "0" "$(cat "$WORK/status")"
}

# A dialog that answers what its arguments list in $DIALOG_ANSWERS, and keeps the arguments it was called with
make_fake_dialog() {
	mkdir -p "$WORK/bin"
	cat >"$WORK/bin/dialog" <<'FAKE'
#!/usr/bin/env bash
echo "$*" >>"$DIALOG_LOG"
answer=$(head -n 1 "$DIALOG_ANSWERS")
sed -i.bak 1d "$DIALOG_ANSWERS" && rm -f "$DIALOG_ANSWERS.bak"
code=${answer%%:*}
text=${answer#*:}
# The result goes where --output-fd says
while [ $# -gt 0 ]; do [ "$1" = --output-fd ] && fd=$2; shift; done
[ -n "${fd:-}" ] && [ -n "$text" ] && printf '%b' "$text" >&"$fd"
exit "$code"
FAKE
	chmod +x "$WORK/bin/dialog"
}

function test_dialog_asks_the_questions_when_it_is_there() {
	make_fake_dialog
	export DIALOG_LOG="$WORK/dialog.log" DIALOG_ANSWERS="$WORK/dialog.answers"
	# One line per dialog: the exit code, then what it prints. A checklist prints one key a line.
	printf '%s\n' '0:Ada' '0:topsecret' '1:' '0:b' '0:x\nz' >"$DIALOG_ANSWERS"
	(
		set -euo pipefail
		export PATH="$WORK/bin:$PATH"
		source "$ROOT/libexec/setup-frontend.sh"
		export OPENSIM_SETUP_UI=dialog FE_TTY=/dev/null FE_SCREEN=/dev/null
		status=0
		frontend_run php "$DRIVER" questions >"$WORK/out" 2>"$WORK/err" </dev/null || status=$?
		echo "$status" >"$WORK/status"
	) 2>/dev/null

	local calls
	calls=$(cat "$DIALOG_LOG" 2>/dev/null)
	assert_contains "--inputbox" "$calls"
	assert_contains "--insecure --passwordbox" "$calls"
	assert_contains "--menu" "$calls"
	assert_contains "--checklist" "$calls"
	assert_equals '{"name":"Ada","password":"topsecret","sure":false,"which":"b","some":["x","z"]}' "$(php -r 'echo json_encode(json_decode(file_get_contents($argv[1])));' "$RESULT")"
	# The notes of the setup are above the first question, not printed by themselves
	assert_contains "Line one" "$calls"
	assert_contains "Test setup" "$calls"
}
