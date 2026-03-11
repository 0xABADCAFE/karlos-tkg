# Modification Data Format

This document describes the binary format used to define the behavioural modification asset files used by the TKG engine.

## Overview

The format is inspired by IFF and comprises a chunk based layout. A file contains a header section, followed by one or more Chunks. Each Chunk has a short form header that identifies the type of data in the Chunk and the total length of the Chunk in bytes, including the short form header.

- All chunks are aligned to 32-bit offsets.

## Basic File Layout

The general structure of the file is shown in the table below. The first 20 bytes encode the [Document Header](../source/SourceFormat.md#document-header)

This is immediately followed by one or more Chunks.


| Offset | Content |
| :---- | :---- |
| 0 | **Ident** `char[4]` |
| 4 | **Subformat** `char[4]` |
| 8 | **Requires** `uint16[2]` Major.Minor |
| 12 | **Version** `uint16[2]` Major.Minor |
| 16 | **Description Offset** `uint32`, offset in string heap chunk |
| 20 | **Chunk 0 Ident** `char[4]` |
| 24 | **Chunk 0 Length** `uint32`, always a multiple of 4 |
| 28 | **Chunk 0 Data** varying, tail padded to 4 byte boundary |
| N \+ 0 | **Chunk 1 Ident** `char[4]` |
| N \+ 4 | **Chunk 1 Length** `uint32`, always a multiple of 4 |
| N \+ 8 | **Chunk 1 Data** varying, tail padded to 4 byte boundary |
| … | … |
| Z \+ 0 | **Chunk N Ident** `char[4]` |
| Z \+ 4 | **Chunk N Length** `uint32`, always a multiple of 4 |
| Z \+ 8 | **Chunk N Data** varying, tail padded to 4 byte boundary |

## Common Chunks

Depending on the Subformat, various Chunk types are defined that are documented in their repsective pages. The following Chunk types are universal.

### Index Chunk

The Index Chunk contains a list of all of the Chunks in the file, complete with their Ident and Offset relative to the start of the file. While an Index Chunk is not mandatory, if present the convention is that it is the first Chunk after the header, thereby having a fixed loction to facilitate locating the resources in the file more easily.

### String Chunk

The String Chunk contains all of the unique strings that are encountered, which are then referenced by offset in other locations. This includes the Description string from the Header. Since this is a mandatory field, it follows that all valid files contain the String Chunk. By convention, the String Chunk is the last chunk in the file.
