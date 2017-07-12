echo "Adding the packages to your PYTHONPATH"
cd ..
pushd redi2
pushd packages
printf "export PYTHONPATH=$PYTHONPATH:%s \n" $(pwd) > ../correct_path.env
source ../correct_path.env
popd

echo "Creating a virtualenv to hold everything"
python3 -m venv ./venv
source venv/bin/activate

pushd repos
bash ../../build_scripts/install_local_packages.sh
popd # leaving repos
popd # leaving redi2

echo "Adding a NEW_SITE"
bash build_scripts/new_site.sh

echo "All set up."
echo "Now edit your configs and the run script in the redi2/NEW_SITE."
