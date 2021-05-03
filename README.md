<p align="center"><a href="https://docs.docker.com/" target="_blank">Docker</a></p>

## About Docker

Docker is an open platform for developing, shipping, and running applications. Docker enables you to separate your applications from your infrastructure so you can deliver software quickly. With Docker, you can manage your infrastructure in the same ways you manage your applications. By taking advantage of Docker’s methodologies for shipping, testing, and deploying code quickly, you can significantly reduce the delay between writing code and running it in production.

- Develop your application and its supporting components using containers.
- The container becomes the unit for distributing and testing your application.
- When you’re ready, deploy your application into your production environment, as a container or an orchestrated service. This works the same whether your production environment is a local data center, a cloud provider, or a hybrid of the two.


## Docker Images

An image is a read-only template with instructions for creating a Docker container. Often, an image is based on another image, with some additional customization. For example, you may build an image which is based on the ubuntu image, but installs the Apache web server and your application, as well as the configuration details needed to make your application run.

You might create your own images or you might only use those created by others and published in a registry. To build your own image, you create a Dockerfile with a simple syntax for defining the steps needed to create the image and run it. Each instruction in a Dockerfile creates a layer in the image. When you change the Dockerfile and rebuild the image, only those layers which have changed are rebuilt. This is part of what makes images so lightweight, small, and fast, when compared to other virtualization technologies.

## Docker Containers

A container is a runnable instance of an image. You can create, start, stop, move, or delete a container using the Docker API or CLI. You can connect a container to one or more networks, attach storage to it, or even create a new image based on its current state.

By default, a container is relatively well isolated from other containers and its host machine. You can control how isolated a container’s network, storage, or other underlying subsystems are from other containers or from the host machine.

A container is defined by its image as well as any configuration options you provide to it when you create or start it. When a container is removed, any changes to its state that are not stored in persistent storage disappear.

### Docker Installation Link

- https://bitpress.io/simple-approach-using-docker-with-php/

## Start Docker

- sudo systemctl start docker 

## error: Cannot start service app: error while creating mount source path '/var/www/html/laravel-docker': mkdir /var/www

- Solution: sudo systemctl restart docker

## If Docker installed on remote machine then we can start it using

- docker-machine 
- start docker-machine

## Check the status of Docker

- Sudo systemctl status docker

## Gives the permission to docker machine

    - sudo groupadd user_name
     
    ## Add your user to the docker group
      
      - sudo usermod -aG user_name ${USER}

    ## type the following command:

        su ${USER}

    ## Verify that you can run docker commands without sudo.

        docker run hello-world

## Check docker running process

    docker ps

## Running Docker with container

    docker exec -it container name

## Laravel migration issue are coming with mysql 8.0 in docker .And solved using followings:

   ## Stop all service: 

      - docker-compose down
      - Edit .env file set MYSQL_VERSION=5.7 or in docker-compose.yml MYSQL_VERSION= 5.7
