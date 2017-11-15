echo "Installing packages"
source ../venv/bin/activate
pip3 install -e ./cappy
pip3 install -e ./claw
pip3 install -e ./auditor
pip3 install --process-dependency-links -e ./optimus
pip3 install --process-dependency-links -e ./lineman
pip3 install --process-dependency-links -e ./pigeon
