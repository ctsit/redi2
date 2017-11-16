# NOTE unlike the other build scripts, this one needs to be run
# one level up.
# $ bash build_scripts/new_site.sh
echo "Making new site with folder named NEW_SITE in the redi2 folder"

echo "Bringing over sample configs into example site"
pushd redi2
mkdir NEW_SITE
pushd NEW_SITE
mkdir configs
mkdir data
mkdir -p log/archive
cp ../repos/claw/claw/config.yaml.example configs/claw.conf.yaml
cp ../repos/auditor/auditor/auditor.example.conf.yaml configs/auditor.conf.yaml
cp ../repos/auditor/auditor/configs/* configs/
cp ../repos/optimus/optimus/configs/* configs/
cp ../repos/lineman/lineman/lineman.example.conf.yaml configs/lineman.conf.yaml
cp ../repos/pigeon/pigeon/pigeon.example.conf.yaml configs/pigeon.conf.yaml
cp ../repos/hawk_eye_notify/hawk_eye_notify/hawk_eye_notify.conf.yaml.example configs/hawk_eye_notify.conf.yaml

echo "Building a simple run script"
cat ../../build_scripts/run_template.sh > run.sh
chmod +x run.sh

popd # leaving NEW_SITE
popd # leaving redi2

echo "All set up. Now edit your configs and run script."
echo "Also make sure to rename your site folder."

