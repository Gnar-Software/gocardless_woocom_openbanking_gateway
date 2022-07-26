PLUGIN_NAME=$(basename "$PWD")
PLUGIN_PATH=$PWD
NEW_PLUGIN_NAME=${PLUGIN_NAME/./}
SVN_TRUNK_DIR=svn-"${NEW_PLUGIN_NAME}"/"${NEW_PLUGIN_NAME}"/trunk/

# copies it to local repo ~/Documents/gnar/gnar_repo/test_deployment/
cp -r $PLUGIN_PATH $SVN_TRUNK_DIR

# change to SVN trunk dir
cd $SVN_TRUNK_DIR

# remove '.' from folder name
mv "${PLUGIN_NAME}" "${NEW_PLUGIN_NAME}"

# deletes .git, readme, and the bash script
cd "${NEW_PLUGIN_NAME}"
sudo rm -rf .git README.md local_deploy.sh svn_local_deploy.sh .gitignore
cd ..

