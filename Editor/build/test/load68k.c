#include <stdio.h>
#include <stdint.h>
#include <stdlib.h>

#define ALIGN(n) __attribute__((packed)) __attribute__ ((aligned (n)))


typedef union {
	uint32_t offset;
	char const* string;
} ALIGN(sizeof(uint32_t)) StrOffset;

typedef struct {
	uint16_t major, minor;
} ALIGN(sizeof(uint32_t)) Version;

typedef struct {
	char ident[4];
	char subformat[4];
	Version requires;
	Version version;
	StrOffset description;
} ALIGN(sizeof(uint32_t)) Header;


void* loadfile(char const* filename) {
	FILE* fp = fopen(filename, "rb");
	fseek(fp, 0, SEEK_END);
	int32_t length = ftell(fp);
	fseek(fp, 0, SEEK_SET);

	
	printf("File length %d bytes\n", length);

	void* buffer = calloc(length, 1);
	
	if (buffer) {
		fread(buffer, length, 1, fp);
		printf("Loaded to %p\n", buffer);
		
		Header* header = (Header*)buffer;		
		printf("Ident     %.*s\n", 4, header->ident);
		printf("Subformat %.*s\n", 4, header->subformat);
		printf("Requires  %d.%d\n", (int)header->requires.major, (int)header->requires.minor);
		printf("Version   %d.%d\n", (int)header->version.major, (int)header->version.minor);
	}
	
	fclose(fp);
	return buffer;
}

int main(void) {
	printf("sizeof Header = %u\n", (unsigned) sizeof(Header));
	
	void* data = loadfile("mods/redux.props");
	
	if (data) {
	

		free(data);
	}
	
	return 0;
}
