echo "Loading Synthetic data!"
cd ..

echo "Getting source files"
pushd redi2
source venv/bin/activate

popd
pushd synthetic_data

echo "Copying synthetic data to NEW_SITE"
cp synth.csv ../redi2/NEW_SITE/data/synth.csv

echo "Loading pigeon data into the redcap"
pigeon pigeon_init.json pigeon.conf.yaml

echo "Done"
echo "If the pigeon run errored, make sure that you put the corrent token into \
 the pigeon.conf.yaml in the redi2/synthetic_data directory"

