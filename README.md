GitHub Labels syncer
====================

Ensures you're GitHub repositories have the correct labels.

*Does not remove existing labels or updates issue/pull request.*

You need at least PHP 5.4, Composer and a GitHub account.

> GitHub enterprise is not supported yet :cry:
> But if you need this, please create an issue :blush:

Features
--------

* Adds labels missing in the repository.
* Updates labels with correct word casing and color.
* Informs which labels are present in the repository, but not
  in your local config file.

Installation
------------

Installation is very easy, assuming you just want to get started.

Clone the repository.

```
git clone https://github.com/sstok/github-labels-syncer.git gh-label-sync
cd gh-label-sync
```

Download composer.

```bash
php -r "readfile('https://getcomposer.org/installer');" | php
```

Install the dependencies.

```bash
composer.phar install
```

Configuration
-------------

First, copy config.php.dist to config.php

Now before you can start syncing you're labels you need to create an OAuth token.
Create one at https://github.com/settings/tokens/new

**You only need "repo", so un checking everything else is a good idea :+1:**

Fire-up you're favorite editor and paste the token as the value of 'token'
(replace 'change-me' with you're new token).

Done? Ok, lets configure some labels.

*To give you some inspiration there are already
some labels pre-configured.*

'labels' in config.php has a very simple structure.
Each key is a label-name and the value is a [hex-color](http://www.color-hex.com/).

**Tip:** Don't worry about letter casing, all labels are lowercased when comparing.
So having label "Bug" locally and "bug" in the repository will rename "bug"
in the repository to "Bug".

Usage
-----

Usage is very simple, after you installed the dependencies and configured
you're token and labels run:

```bash
php syncer.php <org> <repository>
```

\<org\> is the GitHub organization the repository
is housed in.

\<repository\> is the repository-name of the repository
you want to update.

Or if you're not sure run with `--dry-run`

```bash
php syncer.php --dry-run <org> <repository>
```

Which only tells what would have been done, but does not update
the actual repository.

LICENSE
-------

[MIT](LICENSE)
