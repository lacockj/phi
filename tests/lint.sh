#!/bin/bash

DIR0=$(dirname "$0")
main() {
    set -exu
    cd ..
    tmpf=$(mktemp)
    find . -name "*.php" ! -path "./vendor/*" -exec php -l {} 2>&1 \; > $tmpf
    if grep "syntax error, unexpected" $tmpf ; then
        die "ERROR: Found syntax errors:" 3 "$tmpf"
    fi
}
die() {
    echo "${1:-ERROR}" 1>&2 ;
    catf=${3:-} ;
    [[ -z $catf ]] || cat "$catf" ;
    exit ${2:-2} ;
}
cd "$DIR0" && main "$@"
