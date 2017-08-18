source ../venv/bin/activate
# the disconnected environment requires particular versions of dependencies for claw
#pip3 install --target ../packages -r ../../build_scripts/frozen_deps.txt
for dir in `find . -type d`
do
    pip3 download --no-cache-dir -d ../packages -e ./dir
done
deactivate
