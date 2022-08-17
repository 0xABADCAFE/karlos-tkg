/*
   TKG File compressor/Decompressor V0.0
*/
init:
   parse arg c_line
   address COMMAND
   file.name = c_line
   file.head = ""
   file.action = "none"

main:
   if readfileheader(file.name) then
      call unpackfile(file.name)
   else
      call packfile(file.name)

exit:
   exit 5

error:
   say "Error : "arg(1)
   exit 10


readfileheader:
   say "Checking file "arg(1)
   if ~open(SRCFILE,arg(1),R) then call error("Couldn't open file '"arg(1)"'")
   file.head = readch(SRCFILE,4)
   call close (SRCFILE)
   if file.head = "=SB=" then return 1
   return 0

unpackfile:
   say "Unpacking "arg(1)
   'xfddecrunch 'arg(1)
   return

packfile:
   say "Packing "arg(1)
   'ab3edit:editors/utils/lha3 a ram:clip 'arg(1)' cspeed=5 hdrlvl=0 >nil:'
   'delete ram:clip.lha.cat quiet'
   'ab3edit:editors/utils/lhaconv ram:clip.lha 'arg(1)' >nil:'
   'delete ram:clip.lha quiet'
   return
