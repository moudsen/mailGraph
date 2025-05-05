## Introduction ##
With the following instructions, mailGraph can be run in a Docker container.
Every new version of mailGraph will also be deployed to Docker Hub.

## Instructions - Plain vanilla deployment ##
- Create a directory structure on your system as follows:
  ```
  mkdir /opt/Zabbix-Mailgraph
  mkdir /opt/Zabbix-Mailgraph/config
  mkdir /opt/Zabbix-Mailgraph/templates
  ```
- Create a new `config.json` in the config directory and configure accordingly (refer to the wiki for more detailed instructions or use the `config.json.template` as a boilerplate).
- Deploy the mailGraph container, preferably using `docker-compose`.
-- Adopt and configure the `docker-compose.yml` file to your needs;
-- Configure `RESTART_POLICY=unless-stopped` in a file name `.env` (same directory as `docker-compose`).
- Start the container: `docker-compose -D up`

## Testing and Debugging ##
- Display currently running containers `docker ps`
- Execute `docker exec -it <container ID> sh` for a shell into the container
- Following the instructions in the Wiki on troubleshooting and debugging
- When done testing, `exit` the container

## Periodic cleanup of images and logs ##
In case you like to adjust the container to your needs, you can adopt and modiyfy the provisioned scripts and files in this directory.

## Special thank you ##
I would like to express my gratitude to the following people that have actively contributed to bring bugs and improvements to my attention with regards for mailGraph on Docker:
- [dima-online](https://github.com/dima-online)
