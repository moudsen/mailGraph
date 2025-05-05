## Introduction ##
With the following instructions, mailGraph can be run in a Docker container.
Every new version of mailGraph will also be deployed to Docker Hub.

WORK-IN-PROGRESS

## Instructions - Plain vanilla deployment ##
- Create a directory structure on your system as follows:
  ```
  mkdir /opt/Zabbix-Mailgraph
  mkdir /opt/Zabbix-Mailgraph/config
  mkdir /opt/Zabbix-Mailgraph/templates
  ```
- Create a new `config.json` in the above `config` directory and configure accordingly (refer to the wiki for more detailed instructions or use the `config.json.template` as a boilerplate).
- Copy the `plain.template` and `html.template` into the above `templates` directory.
- Deploy the mailGraph container, preferably using `docker-compose`:
-- Adopt and configure the `docker-compose.yml` file to your needs;
-- Configure `RESTART_POLICY=unless-stopped` in a file name `.env` (same directory as `docker-compose`).
- Start the container: `docker-compose -D up`.
- Follow best-practices on your applicable Linux version to ensure the container is started after a reboot.

When running:
- mailGraph is exposed on port `9080` (point the Zabbix webhook to this location).
- Apache logging is exposed on `/opt/Zabbix-Mailgraph/apache.log`.

## Testing and Debugging ##
- Display currently running containers `docker ps`.
- Execute `docker exec -it <container name> sh` for a shell into the container.
- Following the instructions in the Wiki on troubleshooting and debugging, i.e.:
-- `cd /var/www/site/public`
-- `php mailGraph.test test`
- When done testing, `exit` the container.

## Periodic cleanup of images and logs ##
The following command must be run on a regular basis to clean up images and logs (add to cron for example):
- `docker exec -it <container name> cleanup`

## Custom deployment ##
In case you like to adjust the container to your needs, you can adopt and modiyfy the provisioned scripts and files in this directory.
Please do not forget to modify the repository name in `build.sh` and `docker-compose.yml`.

## Special thank you ##
I would like to express my gratitude to the following people that have actively contributed to bring bugs and improvements to my attention with regards for mailGraph on Docker:
- [dima-online](https://github.com/dima-online)
