echo "Installing packages"
source ../venv/bin/activate
source ../correct_path.env

pip3 install -f ../packages --no-index --upgrade pip

pip3 install -f ../packages --no-index -e ./cappy
pip3 install -f ../packages --no-index -e ./claw
pip3 install -f ../packages --no-index -e ./auditor
pip3 install -f ../packages --no-index -e ./optimus
pip3 install -f ../packages --no-index -e ./lineman
pip3 install -f ../packages --no-index -e ./pigeon
