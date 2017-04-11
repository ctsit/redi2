echo "Using git to update the repos!"
cd ..
pushd redi2

echo "Git pulling on all repos"
pushd repos

pushd cappy
git pull
popd
pushd claw
git pull
popd
pushd auditor
git pull
popd
pushd optimus
git pull
popd
pushd lineman
git pull
popd
pushd pigeon
git pull
popd

popd # leaving repos
popd # leaving redi2

echo "All repos updated, make sure your configs are forward compatible."

