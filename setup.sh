echo "Deploying RED-I 2!"
mkdir redi2
pushd redi2
virtualenv -p python3 venv
source venv/bin/activate
mkdir repos

echo "Getting source files"
pushd repos
git clone https://github.com/PFWhite/auditor.git
git clone https://github.com/PFWhite/cappy.git
git clone https://github.com/PFWhite/claw.git
git clone https://github.com/PFWhite/optimus.git
git clone https://github.com/PFWhite/lineman.git
git clone https://github.com/PFWhite/pigeon.git

echo "Installing packages in the venv"
pushd cappy
pip3 install -e .
popd
pushd claw
pip3 install -e .
popd
pushd auditor
pip3 install -e .
popd
pushd optimus
pip3 install -e .
popd
pushd lineman
pip3 install -e .
popd
pushd pigeon
pip3 install -e .
popd

popd # leaving repos
popd # leaving redi2

bash new_site.sh

echo "All set up."
echo "Now edit your configs and the run script in the redi2/NEW_SITE."

