@echo off
powershell -NoProfile -Command "Compress-Archive -Path * -DestinationPath release.zip -Force"

