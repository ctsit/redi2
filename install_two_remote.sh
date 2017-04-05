echo "Adding the packages to your PYTHONPATH"
pushd redi2
pushd packages
export PYTHONPATH=$PYTHONPATH:$(pwd)
popd
popd

echo "Creating a virtualenv to hold everything"
python3 -m venv redi2/venv
source venv/bin/activate

pushd repos
bash ../../install_packages.sh
popd # leaving repos
popd # leaving redi2

echo "Adding a NEW_SITE"
bash new_site.sh

echo "All set up."
echo "Now edit your configs and the run script in the redi2/NEW_SITE."
