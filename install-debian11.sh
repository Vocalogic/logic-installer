# Install apt tools
apt-get -y update;
apt-get -y install sudo software-properties-common apt-transport-https lsb-release ca-certificates wget;
# Add Repo for PHP 8.2
wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg;
sh -c 'echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list';
apt-get -y update;
# Get PHP 8.2 and not install apache (add fpm)
apt-get -y install php8.2 php8.2-xml php8.2-fpm nginx;
echo "------------------------------------------------";
echo "        Base Prerequisites Completed            ";
echo "-------------------------------------------------";
echo "1. EDIT YOUR /etc/sudoers FILE and add your user so you can execute commands as root during the install process.";
echo "2. Change to the user that you will be installing Logic under. You cannot continue as root.";
echo "3. Type exit to return to your original user. Then execute the following";
echo "--------------------------------------------------";
echo "wget https://github.com/Vocalogic/logic-installer/raw/master/builds/logic-installer";
echo "chmod 755 ./logic-installer";
echo "./logic-installer install";

