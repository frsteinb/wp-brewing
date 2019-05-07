
INSTALLLOCATION	= Z:/var/www/wordpress/wp-content/plugins/
INSTALLFILES	= wp-brewing.php index.php includes Makefile

tar:
	cd .. ; tar cvzf wp-brewing/wp-brewing.tar.gz wp-brewing/Makefile wp-brewing/README.md wp-brewing/includes wp-brewing/index.php wp-brewing/LICENSE.txt

install:
	scp -pr $(INSTALLFILES) $(INSTALLLOCATION)/wp-brewing/

