Quick guide to getting the server up and running.

Prerequisites
=============
* PHP 5.2.1 or above (PHP 5.3.x is recommended)
* The following extensions must be enabled:
** mbstring
** sockets
** openssl (required only if you want to enable ssl)

Overview
========

This "Getting Started" guide will walk you through the steps necessary to run the Locke demo. The Locke demo is a small virtual world where characters that look like John Locke from the show "Lost" can walk around and speak to each other. The demo serves static file content and uses either long polling or websockets (depending on the user's browser) to keep the characters synchronized across all users' screens.

3 Simple Steps
--------------
# Check out the source from GitHub: https://github.com/chrisnetonline/WaterSpout-Server
# From the top level of the check out run via the command line: `php server.php`
# Goto: `http://localhost:7777/demos/locke`
