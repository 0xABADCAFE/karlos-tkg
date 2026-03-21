CC      := m68k-amigaos-gcc
CFLAGS  := -Wall -Wextra -Ofast
TARGET  := load68k
SRCS    := main.c gmf.c gmod.c lmod.c
OBJS    := $(SRCS:.c=.o)

all: $(TARGET)

$(TARGET): $(OBJS)
	$(CC) $(CFLAGS) -o $(TARGET) $(OBJS)

%.o: %.c
	$(CC) $(CFLAGS) -c $< -o $@

clean:
	rm -f $(OBJS) $(TARGET)
