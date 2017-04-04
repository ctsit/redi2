echo "Making new site with folder named NEW_SITE in the redi2 folder"

echo "Bringing over sample configs into example site"
pushd redi2
mkdir NEW_SITE
pushd NEW_SITE
mkdir configs
mkdir data
mkdir log
cp ../repos/claw/claw/config.yaml.example configs/claw.conf.yaml
cp ../repos/auditor/auditor/auditor.example.conf.yaml configs/auditor.conf.yaml
cp ../repos/auditor/auditor/configs/* configs/
cp ../repos/optimus/optimus/optimus.example.conf.yaml configs/optimus.conf.yaml
cp ../repos/lineman/lineman/lineman.example.conf.yaml configs/lineman.conf.yaml
cp ../repos/pigeon/pigeon/pigeon.example.conf.yaml configs/pigeon.conf.yaml

echo "Building a simple run script"
cat ../../run_template.sh > run.sh
chmod +x run.sh


popd # leaving NEW_SITE
popd # leaving redi2

echo "All set up. Now edit your configs and run script."
echo "Also make sure to rename your site folder."

