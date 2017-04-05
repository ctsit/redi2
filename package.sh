echo "Preparing your package!"
bash setup_directories.sh

echo "Getting source files"
pushd redi2
pushd repos
bash ../../get_source.sh
popd

echo "Installing your packages in in redi2/packages"
mkdir packages
pushd repos
bash ../../install_packages_for_shipping.sh
popd

echo "Removing compiled things from the packages"
find packages/ -name "__pycache__" | xargs rm -rf
find packages/ -name "*.egg-info" | xargs rm -rf
find packages/ -name "*.pyc" | xargs rm -rf

echo "Removing things you will install from source on the target"
find packages/ -name "cappy*" | xargs rm -rf
find packages/ -name "claw*" | xargs rm -rf
find packages/ -name "auditor*" | xargs rm -rf
find packages/ -name "optimus*" | xargs rm -rf
find packages/ -name "lineman*" | xargs rm -rf
find packages/ -name "pigeon*" | xargs rm -rf

echo "Removing the virtualenv"
deactivate
rm -rf venv

echo "Creating a tar archive for transfer"
popd # back at the top level, need to go one more up
cd ..
tar -czf redi2.tar.gz redi2/

echo "Tar created, now send it to the target system"
