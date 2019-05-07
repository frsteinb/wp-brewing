
INSTALLLOCATION	= Z:/var/www/wordpress/wp-content/plugins/
INSTALLFILES	= wp-brewing.php index.php includes Makefile

default: create.sql

styleguide.xml:
	wget https://raw.githubusercontent.com/meanphil/bjcp-guidelines-2015/master/styleguide.xml

create.sql: styleguide.xml create-glossary-pages.xsl
	xsltproc create-glossary-pages.xsl styleguide.xml > create.sql

create: create.sql
	mysql wordpress < create.sql

remove.sql:
	echo 'DELETE FROM `wp_posts` WHERE post_content LIKE "<!-- auto-generated bjcp glossary post-->%";' > remove.sql

remove: remove.sql
	mysql wordpress < remove.sql

tar:
	cd .. ; tar cvzf wp-brewing/wp-brewing.tar.gz wp-brewing/Makefile wp-brewing/README.txt wp-brewing/includes wp-brewing/index.php wp-brewing/LICENSE.txt

clean:
	rm -f remove.sql create.sql styleguide.xml

install:
	scp -pr $(INSTALLFILES) $(INSTALLLOCATION)/wp-brewing/

