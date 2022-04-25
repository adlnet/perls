#!/usr/bin/env bash

#Copy pre-commit git hook
if [[ -d ".git" && -f "scripts/git_hooks/pre-commit" ]]; then
  if [[ ! -d ".git/hooks" ]]; then
    echo "== .git/hooks directory not found. Creating it ..."
    mkdir .git/hooks
  fi
  echo "== Copying scripts/git_hooks/pre-commit to .git/hooks/pre-commit ..."
  cp scripts/git_hooks/pre-commit .git/hooks/pre-commit
fi

