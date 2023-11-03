#!/bin/bash

#
# Most of this script should be used inside ddev container
#

# Relative path to boilerplate variables
BOILERPLATE_FILE_PATH="./.env/boilerplate.env"
# Path in ddev container
MAUTIC_ROOT_FOLDER="/var/www/html"
PLUGIN_NAMESPACE="HelloWorldBundle"


function get_env_value() {
    local k=$1
    echo $(awk -F= -v key="$k" '$1==key { print $2 }' $BOILERPLATE_FILE_PATH)
}

function help_message() {
    echo "[todo]"
}

function script_info() {
    printf '=== BEGIN INFO === \n\n PLUGIN_ROOT_FOLDER: %s \n\n=== END INFO ===\n\n' \
        $PLUGIN_ROOT_FOLDER

}


PLUGIN_ROOT_FOLDER=$(pwd)

if [[ -e $BOILERPLATE_FILE_PATH ]]; then
    MAUTIC_ROOT_FOLDER=$(get_env_value "MAUTIC_ROOT_FOLDER")
    PLUGIN_NAMESPACE=$(get_env_value "PLUGIN_NAMESPACE")
fi;

script_info

command=$1
if [[ $command == "plugin:init:env" ]]; then
    read -p "Mautic root folder (in DDEV container) [/var/www/html]: " mautic_root
    if [[ -z "$mautic_root" ]]; then
        mautic_root=$MAUTIC_ROOT_FOLDER
    fi;

    read -p "New plugin namespace[HelloWorldBundle]: " plugin_namespace
    if [[ -z "$plugin_namespace" ]]; then
        plugin_namespace="$PLUGIN_NAMESPACE"
    fi;

    printf "MAUTIC_ROOT_FOLDER=%s\nPLUGIN_NAMESPACE=%s\n" $mautic_root $plugin_namespace \
        > $BOILERPLATE_FILE_PATH

elif [[ $command == "dev:hooks" ]]; then
    mkdir -p .git/hooks
    cp .devtools/githook.pre-commit .git/hooks/pre-commit
    chmod 755 .git/hooks/pre-commit

elif [[ $command == "dev:fixcs" ]]; then
    cd $MAUTIC_ROOT_FOLDER
    composer fixcs
    cd $PLUGIN_FOLDER

elif [[ $command == "plugin:change:namespace" ]]; then
    # Works only the first time.
    find . -type f -name '*.php' -exec sed -i s/HelloWorldBundle/"$PLUGIN_NAMESPACE"/g {} +
    mv HelloWorldBundle.php "$PLUGIN_NAMESPACE.php"

elif [[ $command == "--help" ]]; then
    help_message

else
    echo "Unknown command: $command. Exiting!"
    exit 1

fi;
