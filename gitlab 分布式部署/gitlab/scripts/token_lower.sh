#!/bin/bash
if [ $# -eq 0 ]
  then
    echo "No arguments supplied"
    exit
fi
lower=$(echo $1 | tr "[A-Z]" "[a-z]")
token=$1
gitlab-psql -c "update ci_runners set token='$lower' where token='$token'"
gitlab-psql -c "select token from ci_runners where token='$lower'"