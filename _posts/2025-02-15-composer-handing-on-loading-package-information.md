---
layout: post
title: Composer update hanging on Loading composer repositories with package information 
---

One of my projects has some private repositories and when I tried to do a composer update it seemed to keep hanging on: "Loading composer repositories with package information".

What you can do to really see if it hanging is to use -vvvv when updating composer.

```
composer update -vvvv
```