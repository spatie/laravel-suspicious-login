---
title: Requirements
weight: 3
---

This package requires **PHP 8.4+** and **Laravel 11+**.

Login attempts are stored with a `json` column, so on MySQL you need version 5.7 or higher.

The `maxmind` geolocation driver also needs the `geoip2/geoip2` package. All other drivers work without extra packages.
