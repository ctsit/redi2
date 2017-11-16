echo "starting red-i 2 run for NEW_SITE"
STARTTIME=$( date )
DATESTR=`date +%Y-%m-%d`
date

echo "stepping into virtualenv"
source ../venv/bin/activate

echo "claw is getting the raw data"
claw configs/claw.conf.yaml

echo "auditor is redacting and changing the csv"
auditor data/claw.output configs/auditor.conf.yaml -o data/auditor.unclean.output

echo "auditor is cleaning the csv"
auditor data/auditor.unclean.output configs/auditor.conf.yaml -o data/auditor.clean.output -c

echo "optimus is transforming the data"
optimus data/auditor.clean.output configs/optimus.conf.yaml -o data/optimus.output

echo "lineman is making sure the data will transfer"
lineman data/optimus.output configs/lineman.conf.yaml -o data/lineman.output -l log/lineman

echo "pigeon is carrying the data to redcap"
pigeon data/lineman.output configs/pigeon.conf.yaml > data/pigeon.output

echo "run completed"

echo "Moving logs to archive"
for f in log/*.log; do
    mv log/"$f" log/archive/"$DATESTR"_"$f"
done

echo "start time"
echo $STARTTIME
echo "end time"
date
