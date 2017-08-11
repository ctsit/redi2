source ../venv/bin/activate
# the disconnected environment requires particular versions of dependencies for claw
pip3 install --no-cache-dir --target ../packages -r ../../build_scripts/frozen_deps.txt
deactivate
