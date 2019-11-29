#!/bin/sh -x
# Server command: nohup /path/to/tcpserver -v -1 0 45986 /path/to/http-proxy-client.sh > /path/to/tcpserver.log 2>&1 &

# get a random port -- this could be improved
port=$(shuf -i 2048-65000 -n 1)
[ "$port" = 45986 ] && port=$(shuf -i 2048-65000 -n 1)

# start the PHP server in the background
### Paste your php builtin server command
pid=$!
sleep 1

# proxy standard in to nc on that port
/path/to/nc localhost "${port}"

# kill the server we started
kill "${pid}"

exit 0
