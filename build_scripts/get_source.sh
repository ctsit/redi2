
#TODO: [A] (senrabc@gmail.com) 20170427 this file is key to bringing in all the
# individual small tools that compose RED-I2. It needs a better description.

# Auditor cleans the input csvs
git clone --branch 1.3.0 https://github.com/ctsit/auditor.git

# Cappy is a redcap api library
git clone --branch 1.2.0 https://github.com/ctsit/cappy.git

# Claw gets files over sftp
git clone --branch 1.2.0 https://github.com/ctsit/claw.git

# Optimus transforms csvs into redcap project specific json
git clone --branch 1.2.0 https://github.com/ctsit/optimus.git

# lineman is the tool to make sure the JSON input data is properly formatted
# to go into REDCAP
git clone --branch 1.2.0 https://github.com/ctsit/lineman.git

# Pigeon brings data to redcap
git clone --branch 1.2.0 https://github.com/ctsit/pigeon.git

# Hawk_eye_notify watches for logs and sends emails
git clone --branch 1.0.0 https://github.com/ctsit/hawk_eye_notify.git
