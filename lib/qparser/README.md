# QParser
Query parser (js prototype, wip)

## How to play
~~Download the files open "parser-4.html" in browser.~~
The lib is a "module", it must be served by a server.

Easy way : open with phpstorm, use embed server (chrome/ffox small icon on top/right)

Todo : docker...

Type a query, or select a preset query.

- The "ft" is the full test query.

- The "tokens" is the list of tokens identified ("words" of grammar), with no syntax control.

  Some groups of tokens are already recognized as a "sub-query", eg. group of consecutive "brown" words, or parenthized blocks.
  
  Some "ghost" tokens (gray parenthesis) may be added during tokenization, to fix priority and help syntax analysis.
  
- The "ast" (abstract syntax tree) is the interpretation of the tokens.

  The ast can be transformed to a query for a specific search engine (as long as the engine supports all types of queries described by the language)
  
  Each cell indicates the type of "node" and the position of the matching string in the original query.
  
  The ø node indicates a missing part in the ast, eg. the ft is incomplete. 
  
## Next step / GOAL :
Using the position of the cursor in the ft and the status of the ast (ø node), guess what can come here and propose autocompletion

## Todo later
add [] tokens (thesaurus search). in fact it could be replaced by a typed-quote, eg. " t"animal" ".

add rules for a strongest analysis, eg. today :
- " a in x y " ==> a in (x y), we should detect that " x y " is not a valid field name __in phraseanet__
  
  we could also consider that x y is a valid field name (it's a string), and let the client reject the syntax.
  
  todo ? enforce internal rules for "single token strings" aka "identifiers" ?

add (many) rules for a better natural language analysis, eg. today :

- " a and b in c " ==> " (a and b) in c " (nonsense) ; could be transformed to " (a in c) and (b in c) "
- ...
