## Setup
### Install PHP
```bash
brew install php
```

### Install Composer
```bash
brew install composer
```
alternative
```bash
mkdir -p ~/.local/bin
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --install-dir=$HOME/.local/bin --filename=composer
php -r "unlink('composer-setup.php');"

# check
ls ~/.local/bin/composer

echo 'export PATH="$HOME/.local/bin:$PATH"' >> ~/.zshrc
source ~/.zshrc

# check version
which composer
composer --version
```


### Install OpenSwoole
```bash
pecl install openswoole
```

if throw error
```bash
brew install pcre2

export CPPFLAGS="-I$(brew --prefix pcre2)/include"
export LDFLAGS="-L$(brew --prefix pcre2)/lib"

pecl install openswoole
```

### Install HasPHP
```bash
composer create-project haslab/hasphp .
```

### Run HasPHP
```bash
php index.php

OpenSwoole http server is started at http://127.0.0.1:9501
```


### Add dev server
Install fswatch
```bash
brew install fswatch
```
then
```bash
chmod +x dev.sh
./dev.sh
```
