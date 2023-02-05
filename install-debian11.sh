# Install apt tools
apt-get -y install sudo software-properties-common install apt-transport-https lsb-release ca-certificates wget
# Add Repo for PHP 8.2
wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg
sh -c 'echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list'
apt-get update
# Get PHP 8.2
apt-get -y install php8.2 php8.2-xml
# Get Logic Installer
wget https://github.com/Vocalogic/logic-installer/blob/master/builds/logic-installer
./logic-installer install
