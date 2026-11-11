---
name: php-readline-completion
description: "Explains how PHP ext/readline bridges readline TAB completion to PHP via readline_completion_function() and php_readline_completion_cb(), the prefix-filter/replacement contract that causes duplicated or missing candidates, byte-offset and word-break semantics, and the readline_info() knobs (completion_append_character, attempted_completion_over). Activate when implementing or debugging TAB completion in a PHP CLI shell, when completion duplicates text on the line, returns no matches, appends a spurious space, or falls back to filename completion, or when candidates contain spaces/punctuation such as role names like 'Υπάλληλος (ΑΦ)'."
license: MIT
metadata:
  author: dsewth
---

# PHP Readline TAB Completion

Knowledge about `php_readline_completion_cb` and `readline_completion_function()`,
gathered from the PHP 8.4 source (`ext/readline/readline.c`) and verified
empirically against `dsewth/simple-hrbac`'s `rbac:shell` in a pty on GNU readline.

## The call chain

1. `readline_completion_function(callable)` stores the callable in the static
   `_readline_completion` (one slot only — registering again replaces the
   previous one) and assigns the C function `php_readline_completion_cb` to
   readline's `rl_attempted_completion_function`. That variable belongs to the
   readline library, not to PHP.
2. When TAB is pressed, readline splits the line at the cursor:
   - `text` — the current word: bytes after the last basic word-break
     character before the cursor;
   - `start` — byte offset in `rl_line_buffer` where that word begins;
   - `end` — byte offset where it ends (equal to `rl_point`, the cursor).
3. `php_readline_completion_cb(text, start, end)` calls the PHP callable with
   `($text, $start, $end)` and branches on the returned value:

| PHP return value | What the C layer does | Observable result |
| --- | --- | --- |
| non-empty `array` | `matches = rl_completion_matches(text, _readline_command_generator)` | candidates filtered, common prefix inserted, list displayed |
| empty `array` | allocates `matches[0] = ""` (a real, non-NULL match — kept because libedit reads `matches[2]`) | readline treats it as a finished completion and appends `completion_append_character` → **spurious space on a no-match TAB** |
| non-array / exception | `matches = NULL` | readline falls through to **filename completion** of the working directory, unless `rl_attempted_completion_over` is set |

## The generator's prefix filter

`_readline_command_generator` walks the returned array **in insertion order**,
`convert_to_string`s every entry and yields it only when

```c
strncmp(entry, text, strlen(text)) == 0
```

i.e. a byte-wise, case-sensitive prefix test against `text` (unless readline's
`completion_case_fold` is on). Consequences:

- **Every candidate you return must literally start with `$text`.** Entries that
  do not are silently dropped — no error, just fewer/zero matches.
- `rl_completion_matches` then builds the result array: `matches[0]` is the
  longest common prefix of all yielded entries — this is the string readline
  **inserts** into the line — while `matches[1..n]` are the individual entries,
  which are only **listed** below the prompt (NULL-terminated).
- If no entry passes the filter, `rl_completion_matches` returns `NULL` →
  filename-completion fallback kicks in (proven by planting a decoy file that
  then appeared in the completion list).

## The replacement contract (root cause of duplicated text)

Readline replaces exactly `rl_line_buffer[start, end)` with `matches[0]`.
Everything outside that span stays untouched, and the listed candidates are
display-only. Therefore:

- **Candidates must be sliced starting at byte `$start`** — return the
  remainder of the argument from that offset, not the full logical argument.
- Returning whole arguments makes readline re-insert the name in front of the
  fragment already on the line:
  `cd Υ` + TAB → `cd Υπάλληλος (`, second TAB → `cd Υπάλληλος (Υπάλληλος (`.
- When the completer works from its own idea of where the argument starts
  (`$argumentStart`), the slice offset is
  `substr($candidate, $textStart - $argumentStart)` with `$textStart = $start`,
  clamped to `>= 0`.
- All offsets are **byte** offsets in the UTF-8 buffer: a Greek character is
  2 bytes. Use `strlen()` / `substr()` / `str_starts_with()`; never `mb_*`.

## Word-break characters

`rl_basic_word_break_characters` default is `" \t\n\"\\'`@$><=;|&{("`.
Verified empirically on PHP 8.4 / GNU readline:

- **Break**: space, tab, newline, `(`, `=`, `{`, `|`, `&`, `>`, `<`, `"`, `'`,
  backtick, `@`, `$`, `;`, `\`
- **Do not break**: `/`, `-`, `.`, `:`, `)` — note the asymmetry that `(`
  breaks but `)` does not.

So for `cd Υπάλληλος (ΑΦ)` with the cursor after `(Α`: `text` is `Α` (not the
whole argument) and `start` points just after the `(`. Role names containing
spaces or parentheses routinely straddle several "words" — the completer must
never assume the word equals the argument.

`start` can be recomputed when not trusted: scan `line_buffer` from the cursor
backwards past these characters (see `ShellCompleter::wordStart()`), or read
the ground truth from `readline_info()`:

```php
$info = readline_info();        // line_buffer, point, end, mark, prompt, ...
$buffer = substr($info['line_buffer'], 0, $info['point']);  // cut at cursor
```

`line_buffer` may contain text past the cursor; always cut it at `point`.

## Knobs via `readline_info()`

- `completion_append_character` (default `' '`) — appended after a successful
  insertion. Set it to `''` when nothing can be completed, otherwise the
  no-match TAB described above still types a space.
- `attempted_completion_over` (default `0`) — set to `1` to declare the
  completion handled: readline will **not** fall back to filename completion.
  Set it on every TAB; leave `completion_append_character` to decide the space.
- `line_buffer` / `point` / `end` — the byte-level view of the current line.

## Completer contract (checklist for a PHP completer)

1. Receive `($text, $start, $end)`; read `line_buffer`, cut it at `point`.
2. Derive the logical arguments from the buffer (own tokenizer), determine
   `$argumentStart` for the argument being completed.
3. Filter candidates with `str_starts_with($candidate, $text)` semantics (the
   C layer re-applies the same test — mirroring it in PHP lets you decide the
   append character yourself).
4. Slice each candidate at `$start - $argumentStart` (clamped to `>= 0`).
5. Return the list; then set
   `readline_info('completion_append_character', $matches === [] ? '' : ' ')`
   and `readline_info('attempted_completion_over', 1)`.
6. Wrap the whole callback in `try/catch` and return `[]` on failure — but
   remember `[]` alone yields the spurious space, so the knobs must be set
   even on the failure path.

## Debugging workflow

- Drive the real command in a pty, send literal `\t`, read back the echoed
  line; assert on the line content, not on the completer's return value —
  the bug lives in the *offset* relation between the two.
- Inside the callback, log `readline_info()` (`text` you got, `start`, `point`,
  `line_buffer`) — that is ground truth for how readline segmented the line.
- Failure signatures:
  - **duplicated name** → candidates not sliced at `$start`;
  - **empty list + trailing space** → empty array returned, append char not
    cleared;
  - **file names appear** → non-empty array but zero entries pass the prefix
    filter (or an exception escaped and `matches` became `NULL`), and
    `attempted_completion_over` was not set;
  - **TAB does nothing** → position/argument detection failed, so no candidate
    matched the typed fragment at all.
- Behaviour was verified on GNU readline under Linux/PHP 8.4. libedit
  (macOS, `HAVE_LIBEDIT`) has a different code path — notably it reads
  `matches[2]`, which is why PHP allocates three slots even for the empty
  array.

## Where this lives in simpleHRBAC

- `src/Console/RbacShellCommand.php` — `registerCompletion()` (bridges the
  callback) and `tuneReadline()` (sets the two `readline_info()` knobs).
- `src/Console/ShellCompleter.php` — `complete($buffer, ?int $textStart)`,
  `candidatesFor()` (filter + slice), `wordStart()` (fallback word-boundary
  detection).
- `src/Console/ShellSession.php` — tokenizer and command execution; must agree
  with the completer about what an "argument" is.
- `tests/Feature/Laravel/ShellCommandTest.php` — completion tests, including
  the offset-slicing regression test.
