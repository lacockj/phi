#!/bin/bash

DIR0=$(dirname "$0")
main() {
    set -exu
    cd ..
    vendor/phploc/phploc/phploc src
}
die() {
    echo "${1:-ERROR}" 1>&2 ;
    catf=${3:-} ;
    [[ -z $catf ]] || cat "$catf" ;
    exit ${2:-2} ;
}
cd "$DIR0" && main "$@"
