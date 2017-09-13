source ../venv/bin/activate
for dir in `ls -d */`
do
    pip3 download --find-links="../packages" --no-cache-dir -d ../packages -e ./$dir
done
pip3 download --find-links="../packages" --no-cache-dir -d ../packages pip
deactivate
