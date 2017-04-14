echo "Installing packages"
source ../venv/bin/activate
pip3 install -f ../packages -e ./cappy
pip3 install -f ../packages -e ./claw
pip3 install -f ../packages -e ./auditor
pip3 install -f ../packages -e ./optimus
pip3 install -f ../packages -e ./lineman
pip3 install -f ../packages -e ./pigeon
