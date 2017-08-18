for dir in `ls -d */`
do
    pip3 download --no-cache-dir -d ../packages -e ./$dir
done
