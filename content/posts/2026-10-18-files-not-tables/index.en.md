---
title: Files, not tables
status: published
published: 2026-10-18 08:00
tags: [wake]
---

Wake has no database. An index kept in PHP's memory makes queries fast, and a
page cache serves finished pages before Laravel even starts.

| Laravel part | In Wake |
|---|---|
| Eloquent | file models |
| Sessions | files |
| Cache | files |
