echo "Deploying RED-I 2!"
cd ..
bash ../../build_scripts/setup_directories.sh

echo "Getting source files"
pushd redi2
pushd repos
bash ../../build_scripts/get_source.sh
bash ../../build_scripts/install_packages.sh

popd # leaving repos
popd # leaving redi2

bash build_scripts/new_site.sh

echo "Check in redi2/redi2/NEW_SITE for an example"
