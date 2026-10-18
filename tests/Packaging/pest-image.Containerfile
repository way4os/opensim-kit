# The PHP of a distribution with only the extensions the tools depend on, to run the pest suites (tests/Packaging/distro-pest);
# php-sqlite3 is for the tests alone: the models are tested on databases in memory
ARG BASE=docker.io/library/debian:bookworm
FROM ${BASE}
RUN apt-get update -qq && DEBIAN_FRONTEND=noninteractive apt-get install -y -qq --no-install-recommends \
    php-cli php-curl php-intl php-mbstring php-mysql php-sqlite3 php-xml ca-certificates tar && apt-get clean
