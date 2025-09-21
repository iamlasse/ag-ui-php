#!/bin/bash

# AG-UI PHP Protocol Buffers Generation Script
# This script generates PHP classes from .proto files

set -e

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${BLUE}AG-UI PHP Protocol Buffers Generator${NC}"
echo "======================================"

# Check if protoc is installed
if ! command -v protoc &> /dev/null; then
    echo -e "${RED}Error: protoc is not installed${NC}"
    echo "Please install Protocol Buffers compiler:"
    echo "  macOS: brew install protobuf"
    echo "  Ubuntu: sudo apt-get install protobuf-compiler"
    echo "  CentOS: sudo yum install protobuf-compiler"
    exit 1
fi

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PACKAGE_DIR="$(dirname "$SCRIPT_DIR")"
PROTO_DIR="$PACKAGE_DIR/src/proto"
OUTPUT_DIR="$PACKAGE_DIR/src/Generated"

echo -e "${BLUE}Configuration:${NC}"
echo "  Proto files: $PROTO_DIR"
echo "  Output dir:  $OUTPUT_DIR"

# Verify proto files exist
if [ ! -d "$PROTO_DIR" ]; then
    echo -e "${RED}Error: Proto directory not found: $PROTO_DIR${NC}"
    exit 1
fi

# Count proto files
PROTO_FILES=$(find "$PROTO_DIR" -name "*.proto" | wc -l)
if [ "$PROTO_FILES" -eq 0 ]; then
    echo -e "${RED}Error: No .proto files found in $PROTO_DIR${NC}"
    exit 1
fi

echo -e "${BLUE}Found $PROTO_FILES .proto files${NC}"

# Create output directory
mkdir -p "$OUTPUT_DIR"

# Clean previous generation (optional - comment out if you want to keep old files)
echo -e "${BLUE}Cleaning previous generated files...${NC}"
rm -rf "$OUTPUT_DIR"/*

# Generate PHP classes
echo -e "${BLUE}Generating PHP classes...${NC}"
protoc \
    --php_out="$OUTPUT_DIR" \
    -I "$PROTO_DIR" \
    "$PROTO_DIR"/*.proto

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ PHP protobuf classes generated successfully!${NC}"
    
    # List generated files
    echo -e "${BLUE}Generated files:${NC}"
    find "$OUTPUT_DIR" -name "*.php" | sed 's|^|  |'
    
    echo ""
    echo -e "${GREEN}Generation complete!${NC}"
    echo "PHP protobuf classes are available in: $OUTPUT_DIR"
else
    echo -e "${RED}✗ Failed to generate PHP classes${NC}"
    exit 1
fi
