# Contributing to Pug-php

## Code of Conduct

We worked hard to provide this pug port. And we get any profit of it. That's why we ask you to be polite and respectful. For example, when you report an issue, please use human-friendly sentences ("Hello", "Please", "Thanks", etc.)

## Issue Contributions

Please report any security issue or risk by emailing pug@selfbuild.fr. Please don't disclose security bugs publicly until they have been handled by us.

For any other bug or issue, please click this link and follow the template if applicable:
[Create new issue](https://github.com/pug-php/pug/issues/new)

This template will help you provide us the informations we need for most issues (the PHP and/or Pug code you use, the expected behaviour and the current behaviour).

## Code Contributions

Fork the [GitHub project](https://github.com/pug-php/pug) and `checkout` your copy locally:

```shell
git clone https://github.com/<username>/pug.git
cd pug
git remote add upstream https://github.com/pug-php/pug.git
```
Replace `<username>` with your GitHub username.

Then, you can work on the master or create a specific branch for your development:

```shell
git checkout -b my-feature-branch -t origin/master
```

You can now edit the "pug" directory contents.

Before committing, please set your name and your e-mail (use the same e-mail address as in your GitHub account):

```shell
git config --global user.name "Your Name"
git config --global user.email "your.email.address@example.com"
```

The ```--global``` argument will apply this setting for all your git repositories, remove it to set only your pug fork with them.

Now you can index and commit your modifications as you usually do with git:

```shell
git add --all
git commit -m "The commit message log"
```

If your patch fixes an open issue, please insert ```#``` immediately followed by the issue number:

```shell
git commit -m "#21 Fix this or that"
```

Use git rebase (not git merge) to sync your work from time to time:

```shell
git fetch origin
git rebase origin/master
```

Please add some tests for bug fixes and features (for example a pug file and the expected HTML file in /tests/templates), then check all is right with phpunit:

Install PHP if you haven't yet, then install composer:
https://getcomposer.org/download/

Update dependencies:
```
./composer.phar update
```

Or if you installed composer globally:
```
composer update
```

Then call phpunit:
```
./vendor/bin/phpunit
```

Make sure all tests succeed before submitting your pull-request, else we will not be able to merge it.

Push your work on your remote GitHub fork with:
```
git push origin my-feature-branch
```

Go to https://github.com/yourusername/pug and select your feature branch. Click the 'Pull Request' button and fill out the form.

We will review it within a few days. And we thank you in advance for your help.
