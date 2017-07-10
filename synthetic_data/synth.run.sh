echo "starting red-i 2 run for NEW_SITE"
STARTTIME=$( date )
date

ACTIVATE_PATH=/home/vagrant/redi2/redi2/venv/bin/activate

echo "stepping into virtualenv"
source ${ACTIVATE_PATH}

# echo "claw is getting the raw data"
# claw configs/claw.conf.yaml

echo "auditor is redacting and changing the csv"
auditor data/synth.csv configs/synth.auditor.conf.yaml -o data/synth.auditor.unclean.output

echo "auditor is cleaning the csv"
auditor data/synth.auditor.unclean.output configs/synth.auditor.conf.yaml -o data/synth.auditor.clean.output -c

echo "optimus is transforming the data"
optimus data/synth.auditor.clean.output configs/synth.optimus.conf.yaml -o data/synth.optimus.output

echo "lineman is making sure the data will transfer"
lineman data/synth.optimus.output configs/synth.lineman.conf.yaml -o data/synth.lineman.output -l log/synth.lineman

echo "pigeon is carrying the data to redcap"
pigeon data/synth.lineman.output configs/synth.pigeon.conf.yaml > data/synth.pigeon.output

echo "run completed"

echo "start time"
echo $STARTTIME
echo "end time"
date
