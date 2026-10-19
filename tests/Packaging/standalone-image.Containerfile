# The core of OpenSimulator and what the engine needs to make a grid and run it, to test a standalone grid
# (tests/Packaging/standalone-scenario.sh): .NET 8, a database, the PHP of the engine
ARG BASE=mcr.microsoft.com/dotnet/sdk:8.0
FROM ${BASE}
RUN apt-get update -qq && DEBIAN_FRONTEND=noninteractive apt-get install -y -qq --no-install-recommends \
    php-cli php-curl php-imagick php-intl php-mbstring php-mysql php-sqlite3 php-xml ca-certificates tar curl \
    mariadb-server libgdiplus libicu72 procps screen && apt-get clean
