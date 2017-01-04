#!/usr/bin/env php
<?php

$autoloader = require __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Console\Application;
use DrupalModuleChecker\CheckModulesCommand;

// Build application.
$application = new Application('Drupal Module Checker', '@git-version@');
$application->add(new CheckModulesCommand());
$application->setDefaultCommand('check_modules', TRUE);
$application->run();
