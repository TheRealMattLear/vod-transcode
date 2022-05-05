#!/bin/bash


# https://stackoverflow.com/questions/26220557/how-to-get-h264-bitrate
# Determine the peak bitrate and average bit rate for a video file.
#
# Counts video stream packets directly so can be used when
# video bitrate metadata is incorrect or missing. Very fast.
#
# Takes a sample of frames and counts max bitrate if more than 5% of frames 
#
# Usage:
# ./vbit.sh <file> <optional: frames to count> <optional: fps (detected automatically if excluded)> 
#
# Examples:
# ./vbit.sh input.mkv 
# ./vbit.sh input.mkv  10000


sampleSize=5000



frames=$2
# Numper of frames to process
: ${frames:=65536}

fps=$3
# defaults to detecting tbr with ffmpeg
: ${fps:=$(/usr/local/mediacp/ffmpeg/bin/ffmpeg -i $1 2>&1 | sed -n "s/.*, \(.*\) tbr.*/\1/p")}

#echo "FPS=$fps"

awk -v FPS="${fps}" -v FRAMES="${frames}" '
BEGIN{
    FS="="
}
/size/ {
  last_br=br
  br=$2/1000.0*8*FPS
  if ( br > peak_br ) peak_br = br;
  
  # peak over last 100 frames
  x+=1
  if ( x > $sampleSize ){
	x=0
	max_br_hit=0
	max_br_acc=0
  }
  if ( br > max_br ){
	max_br_hit+=1
	max_br_acc+=br
  }
  # Only record max_br if we have more than 100 frames of the last 1000 frames were over br
  if ( max_br_hit > 250 ){
	max_br=max_br_acc/max_br_hit
  }
	  
	  
  acc_br+=br
  acc_bytes+=br
  i+=1

  if (i >= FRAMES)
      exit 
}
END {
	if ( acc_br/i > max_br )
		max_br=acc_br/i
		
    # print "----"
	# printf("iterations: %.1f\n",i)
    #printf("AVG=%.0f\n", acc_br/i) # outputs kbps
    printf("%.0f\n", max_br) # outputs kbps
    # printf("Peak BR: %.1fkbits/s\n", peak_br)
}
' <(/usr/local/mediacp/ffmpeg/bin/ffprobe -select_streams v -show_entries packet=size $1 2>/dev/null)
