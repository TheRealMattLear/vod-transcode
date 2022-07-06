#!/bin/bash
# https://github.com/slhck/ffmpeg-bitrate-stats
bitrate=`ffmpeg_bitrate_stats $1 | jq -r '.max_bitrate'`
echo ${bitrate%.*}
