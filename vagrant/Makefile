
VAGR := VAGRANT_CWD=vagrant
VAGR_SSH := $(VAGR) vagrant ssh

help:
	@echo ""
	@echo "Available tasks:"
	@echo
	@echo "  vup                     : vagrant up the VM"
	@echo "  vpro                    : vagrant provision the VM"
	@echo "  vhalt                   : vagrant halt the VM"
	@echo "  vdown                   : vagrant destroy the VM"
	@echo "  vas                     : ssh into the VM"
	@echo "  get_redi								 : setup red-i to run against this VAGRANT"

vup:
	$(VAGR) vagrant up
vpro:
	$(VAGR) vagrant provision
vhalt:
	$(VAGR) vagrant halt
vdown:
	$(VAGR) vagrant destroy
vas:
	$(VAGR_SSH)
get_redi:
	#clone into red-i, use master which will be the latest release
	git clone git@github.com:ctsit/redi.git	
