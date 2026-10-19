#!/bin/bash

# Removes from the git index the files that were added (git add) then deleted
# from the disk without ever being committed: `git status` reports them as
# "AD", while IDEs comparing HEAD to the working tree show nothing.
# Only the index is touched; usage: bin/dev/git-clean-orphan-index.sh [-n]

set -e

DRY_RUN=0
if [ "$1" == "-n" ] || [ "$1" == "--dry-run" ]; then
  DRY_RUN=1
fi

files=()
while IFS= read -r -d '' entry; do
  # Renames and copies are followed by an extra NUL-terminated source path.
  if [[ "${entry:0:1}" == [RC] ]]; then
    IFS= read -r -d '' _
  fi
  if [ "${entry:0:2}" == "AD" ]; then
    files+=("${entry:3}")
  fi
done < <(git status --porcelain=v1 -z --untracked-files=no)

if [ ${#files[@]} -eq 0 ]; then
  echo "No orphan index entry."
  exit 0
fi

printf ' %s\n' "${files[@]}"

if [ $DRY_RUN -eq 1 ]; then
  exit 0
fi

git rm --cached --quiet -- "${files[@]}"
echo "${#files[@]} orphan index entries removed."
