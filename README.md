# 4chan_backuper
script to backup 4chan threads and alter the html to link to local backuped images.

# usage


```bash
$ php 4chan_backuper.php 'http://boards.4channel.org/ck/thread/12210415#p12210415'

got url from $argv
url parsed: "http://boards.4channel.org/ck/thread/12210415"
making folder "/cygdrive/c/projects/4chan_backuper/backups/ck"... done.
making folder "/cygdrive/c/projects/4chan_backuper/backups/ck/12210415"... done.
making folder "/cygdrive/c/projects/4chan_backuper/backups/ck/12210415/images"... done.
making folder "/cygdrive/c/projects/4chan_backuper/backups/ck/12210415/images/thumbnails"... done.
fetching "http://boards.4channel.org/ck/thread/12210415"..http code "200".fetching "http://i.4cdn.org/ck/1555820343183s.jpg"..http code "200".fetching "http://i.4cdn.org/ck/1555820343183.webm"..http code "200".fetching "http://i.4cdn.org/ck/1555821226814s.jpg"..http code "200".fetching "http://i.4cdn.org/ck/1555821226814.webm"..http code "200".fetching "http://i.4cdn.org/ck/1555821468264s.jpg"..http code "200".fetching "http://i.4cdn.org/ck/1555821468264.webm"..http code "200".fetching "http://i.4cdn.org/ck/1555821607137s.jpg"..http code "200".fetching "http://i.4cdn.org/ck/1555821607137.webm"..http code "200".fetching "http://i.4cdn.org/ck/1555821799046s.jpg"..http code "200".fetching "http://i.4cdn.org/ck/1555821799046.webm"..http code "200".fetching "http://i.4cdn.org/ck/1555822097194s.jpg"..http code "200".fetching "http://i.4cdn.org/ck/1555822097194.webm"..http code "200".fetching "http://i.4cdn.org/ck/1555822749436s.jpg"..http code "200".fetching "http://i.4cdn.org/ck/1555872895752.png"..http code "200".
(...capped...)
new posts: 121 - new images: 38
sleeping 10 seconds and refetching...
fetching again.
fetching "http://boards.4channel.org/ck/thread/12210415"..http code "200".new posts: 1 - new images: 0
sleeping 10 seconds and refetching...
fetching again.
fetching "http://boards.4channel.org/ck/thread/12210415"..http code "200".new posts: 0 - new images: 0
sleeping 10 seconds and refetching..
```
