echo "Deploying RED-I 2!"
bash setup_directories.sh

echo "Getting source files"
pushd repos
bash ../../get_source.sh
bash ../../install_packages.sh

popd # leaving repos
popd # leaving redi2

bash new_site.sh

echo "All set up."
echo "Now edit your configs and the run script in the redi2/NEW_SITE."

