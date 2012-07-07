# ***** BEGIN LICENSE BLOCK *****
# Version: MIT
#
# Portions created by VMware are Copyright (c) 2007-2012 VMware, Inc.
# All Rights Reserved.
#
# Portions created by Tony Garnock-Jones are Copyright (c) 2009-2010
# VMware, Inc. and Tony Garnock-Jones. All Rights Reserved.
#
# Permission is hereby granted, free of charge, to any person
# obtaining a copy of this software and associated documentation
# files (the "Software"), to deal in the Software without
# restriction, including without limitation the rights to use, copy,
# modify, merge, publish, distribute, sublicense, and/or sell copies
# of the Software, and to permit persons to whom the Software is
# furnished to do so, subject to the following conditions:
#
# The above copyright notice and this permission notice shall be
# included in all copies or substantial portions of the Software.
#
# THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
# EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
# MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
# NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS
# BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN
# ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
# CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
# SOFTWARE.
# ***** END LICENSE BLOCK *****

from __future__ import nested_scopes

from amqp_codegen import *
import string
import re


class Emitter(object):
    """An object the trivially emits generated code lines.

    This largely exists to be wrapped by more sophisticated emitter
    classes.
    """

    def __init__(self, prefix):
        self.prefix = prefix

    def emit(self, line):
        """Emit a line of generated code."""
        print self.prefix + line


class BitDecoder(object):
    """An emitter object that keeps track of the state involved in
    decoding the AMQP bit type."""

    def __init__(self, emitter):
        self.emitter = emitter
        self.bit = 0

    def emit(self, line):
        self.bit = 0
        self.emitter.emit(line)

    def decode_bit(self, lvalue):
        """Generate code to decode a value of the AMQP bit type into
        the given lvalue."""
        if self.bit == 0:
            self.emitter.emit("if (!amqp_decode_8(encoded, &offset, &bit_buffer)) return -ERROR_BAD_AMQP_DATA;")

        self.emitter.emit("%s = (bit_buffer & (1 << %d)) ? 1 : 0;"
                                                        % (lvalue, self.bit))
        self.bit += 1
        if self.bit == 8:
            self.bit = 0


class BitEncoder(object):
    """An emitter object that keeps track of the state involved in
    encoding the AMQP bit type."""

    def __init__(self, emitter):
        self.emitter = emitter
        self.bit = 0

    def flush(self):
        """Flush the state associated with AMQP bit types."""
        if self.bit:
            self.emitter.emit("if (!amqp_encode_8(encoded, &offset, bit_buffer)) return -ERROR_BAD_AMQP_DATA;")
            self.bit = 0

    def emit(self, line):
        self.flush()
        self.emitter.emit(line)

    def encode_bit(self, value):
        """Generate code to encode a value of the AMQP bit type from
        the given value."""
        if self.bit == 0:
            self.emitter.emit("bit_buffer = 0;")

        self.emitter.emit("if (%s) bit_buffer |= (1 << %d);"
                                                       % (value, self.bit))
        self.bit += 1
        if self.bit == 8:
            self.flush()


class SimpleType(object):
    """A AMQP type that corresponds to a simple scalar C value of a
    certain width."""

    def __init__(self, bits):
        self.bits = bits
        self.ctype = "uint%d_t" % (bits,)

    def decode(self, emitter, lvalue):
        emitter.emit("if (!amqp_decode_%d(encoded, &offset, &%s)) return -ERROR_BAD_AMQP_DATA;" % (self.bits, lvalue))

    def encode(self, emitter, value):
        emitter.emit("if (!amqp_encode_%d(encoded, &offset, %s)) return -ERROR_BAD_AMQP_DATA;" % (self.bits, value))

    def literal(self, value):
        return value

class StrType(object):
    """The AMQP shortstr or longstr types."""

    def __init__(self, lenbits):
        self.lenbits = lenbits
        self.ctype = "amqp_bytes_t"

    def decode(self, emitter, lvalue):
        emitter.emit("{")
        emitter.emit("  uint%d_t len;" % (self.lenbits,))
        emitter.emit("  if (!amqp_decode_%d(encoded, &offset, &len)" % (self.lenbits,))
        emitter.emit("      || !amqp_decode_bytes(encoded, &offset, &%s, len))" % (lvalue,))
        emitter.emit("    return -ERROR_BAD_AMQP_DATA;")
        emitter.emit("}")

    def encode(self, emitter, value):
        emitter.emit("if (!amqp_encode_%d(encoded, &offset, %s.len)" % (self.lenbits, value))
        emitter.emit("    || !amqp_encode_bytes(encoded, &offset, %s))" % (value,))
        emitter.emit("  return -ERROR_BAD_AMQP_DATA;")

    def literal(self, value):
        if value != '':
            raise NotImplementedError()

        return "amqp_empty_bytes"

class BitType(object):
    """The AMQP bit type."""

    def __init__(self):
        self.ctype = "amqp_boolean_t"

    def decode(self, emitter, lvalue):
        emitter.decode_bit(lvalue)

    def encode(self, emitter, value):
        emitter.encode_bit(value)

    def literal(self, value):
        return {True: 1, False: 0}[value]

class TableType(object):
    """The AMQP table type."""

    def __init__(self):
        self.ctype = "amqp_table_t"

    def decode(self, emitter, lvalue):
        emitter.emit("{")
        emitter.emit("  int res = amqp_decode_table(encoded, pool, &(%s), &offset);" % (lvalue,))
        emitter.emit("  if (res < 0) return res;")
        emitter.emit("}")

    def encode(self, emitter, value):
        emitter.emit("{")
        emitter.emit("  int res = amqp_encode_table(encoded, &(%s), &offset);" % (value,))
        emitter.emit("  if (res < 0) return res;")
        emitter.emit("}")

    def literal(self, value):
        raise NotImplementedError()

types = {
    'octet': SimpleType(8),
    'short': SimpleType(16),
    'long': SimpleType(32),
    'longlong': SimpleType(64),
    'shortstr': StrType(8),
    'longstr': StrType(32),
    'bit': BitType(),
    'table': TableType(),
    'timestamp': SimpleType(64),
}

def typeFor(spec, f):
    """Get a representation of the AMQP type of a field."""
    return types[spec.resolveDomain(f.domain)]

def c_ize(s):
    s = s.replace('-', '_')
    s = s.replace(' ', '_')
    return s

# When generating API functions corresponding to synchronous methods,
# we need some information that isn't in the protocol def: Some
# methods should not be exposed, indicated here by a False value.
# Some methods should be exposed but certain fields should not be
# exposed as parameters.
apiMethodInfo  = {
    "amqp_connection_start": False, # application code should not use this
    "amqp_connection_secure": False, # application code should not use this
    "amqp_connection_tune": False, # application code should not use this
    "amqp_connection_open": False, # application code should not use this
    "amqp_connection_close": False, # needs special handling
    "amqp_channel_open": ["out_of_band"],
    "amqp_channel_close": False, # needs special handling
    "amqp_access_request": False, # huh?
    "amqp_exchange_declare": ["auto_delete", "internal"],
    "amqp_basic_get": False, # get-ok has content
}

# When generating API functions corresponding to synchronous methods,
# some fields should be suppressed everywhere.  This dict names those
# fields, and the fixed values to use for them.
apiMethodsSuppressArgs = {"ticket": 0, "nowait": False}

AmqpMethod.defName = lambda m: cConstantName(c_ize(m.klass.name) + '_' + c_ize(m.name) + "_method")
AmqpMethod.fullName = lambda m: "amqp_%s_%s" % (c_ize(m.klass.name), c_ize(m.name))
AmqpMethod.structName = lambda m: m.fullName() + "_t"

AmqpClass.structName = lambda c: "amqp_" + c_ize(c.name) + "_properties_t"

def methodApiPrototype(m):
    fn = m.fullName()
    info = apiMethodInfo.get(fn, [])

    args = []
    for f in m.arguments:
        n = c_ize(f.name)
        if n in apiMethodsSuppressArgs or n in info:
            continue

        args.append(", ")
        args.append(typeFor(m.klass.spec, f).ctype)
        args.append(" ")
        args.append(n)

    return "%s_ok_t *%s(amqp_connection_state_t state, amqp_channel_t channel%s)" % (fn, fn, ''.join(args))

AmqpMethod.apiPrototype = methodApiPrototype

def cConstantName(s):
    return 'AMQP_' + '_'.join(re.split('[- ]', s.upper()))

def cFlagName(c, f):
    return cConstantName(c.name + '_' + f.name) + '_FLAG'

def genErl(spec):
    def fieldTempList(fields):
        return '[' + ', '.join(['F' + str(f.index) for f in fields]) + ']'

    def fieldMapList(fields):
        return ', '.join([c_ize(f.name) + " = F" + str(f.index) for f in fields])

    def genLookupMethodName(m):
        print '    case %s: return "%s";' % (m.defName(), m.defName())

    def genDecodeMethodFields(m):
        print "    case %s: {" % (m.defName(),)
        if m.arguments:
            print "      %s *m = (%s *) amqp_pool_alloc(pool, sizeof(%s));" % \
                (m.structName(), m.structName(), m.structName())
            print "      if (m == NULL) { return -ERROR_NO_MEMORY; }"
        else:
            print "      %s *m = NULL; /* no fields */" % (m.structName(),)

        emitter = BitDecoder(Emitter("      "))
        for f in m.arguments:
            typeFor(spec, f).decode(emitter, "m->"+c_ize(f.name))

        print "      *decoded = m;"
        print "      return 0;"
        print "    }"

    def genDecodeProperties(c):
        print "    case %d: {" % (c.index,)
        print "      %s *p = (%s *) amqp_pool_alloc(pool, sizeof(%s));" % \
              (c.structName(), c.structName(), c.structName())
        print "      if (p == NULL) { return -ERROR_NO_MEMORY; }"
        print "      p->_flags = flags;"

        emitter = Emitter("      ")
        for f in c.fields:
            emitter.emit("if (flags & %s) {" % (cFlagName(c, f),))
            typeFor(spec, f).decode(emitter, "p->"+c_ize(f.name))
            emitter.emit("}")

        print "      *decoded = p;"
        print "      return 0;"
        print "    }"

    def genEncodeMethodFields(m):
        print "    case %s: {" % (m.defName(),)
        if m.arguments:
            print "      %s *m = (%s *) decoded;" % (m.structName(), m.structName())

        emitter = BitEncoder(Emitter("      "))
        for f in m.arguments:
            typeFor(spec, f).encode(emitter, "m->"+c_ize(f.name))
        emitter.flush()

        print "      return offset;"
        print "    }"

    def genEncodeProperties(c):
        print "    case %d: {" % (c.index,)
        if c.fields:
            print "      %s *p = (%s *) decoded;" % (c.structName(), c.structName())

        emitter = Emitter("      ")
        for f in c.fields:
            emitter.emit("      if (flags & %s) {" % (cFlagName(c, f),))
            typeFor(spec, f).encode(emitter, "p->"+c_ize(f.name))
            emitter.emit("}")

        print "      return offset;"
        print "    }"

    methods = spec.allMethods()

    print """/* Autogenerated code. Do not edit.
 *
 * ***** BEGIN LICENSE BLOCK *****
 * Version: MIT
 *
 * Portions created by VMware are Copyright (c) 2007-2012 VMware, Inc.
 * All Rights Reserved.
 *
 * Portions created by Tony Garnock-Jones are Copyright (c) 2009-2010
 * VMware, Inc. and Tony Garnock-Jones. All Rights Reserved.
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use, copy,
 * modify, merge, publish, distribute, sublicense, and/or sell copies
 * of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS
 * BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN
 * ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
 * CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 * ***** END LICENSE BLOCK *****
 */

#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <stdio.h>

#include "amqp.h"
#include "amqp_framing.h"
#include "amqp_private.h"
#include "socket.h"
"""

    print """
char const *amqp_constant_name(int constantNumber) {
  switch (constantNumber) {"""
    for (c,v,cls) in spec.constants:
        print "    case %s: return \"%s\";" % (cConstantName(c), cConstantName(c))
    print """    default: return "(unknown)";
  }
}"""

    print """
amqp_boolean_t amqp_constant_is_hard_error(int constantNumber) {
  switch (constantNumber) {"""
    for (c,v,cls) in spec.constants:
        if cls == 'hard-error':
            print "    case %s: return 1;" % (cConstantName(c),)
    print """    default: return 0;
  }
}"""

    print """
char const *amqp_method_name(amqp_method_number_t methodNumber) {
  switch (methodNumber) {"""
    for m in methods: genLookupMethodName(m)
    print """    default: return NULL;
  }
}"""

    print """
amqp_boolean_t amqp_method_has_content(amqp_method_number_t methodNumber) {
  switch (methodNumber) {"""
    for m in methods:
        if m.hasContent:
            print '    case %s: return 1;' % (m.defName())
    print """    default: return 0;
  }
}"""

    print """
int amqp_decode_method(amqp_method_number_t methodNumber,
                       amqp_pool_t *pool,
                       amqp_bytes_t encoded,
                       void **decoded)
{
  size_t offset = 0;
  uint8_t bit_buffer;

  switch (methodNumber) {"""
    for m in methods: genDecodeMethodFields(m)
    print """    default: return -ERROR_UNKNOWN_METHOD;
  }
}"""

    print """
int amqp_decode_properties(uint16_t class_id,
                           amqp_pool_t *pool,
                           amqp_bytes_t encoded,
                           void **decoded)
{
  size_t offset = 0;

  amqp_flags_t flags = 0;
  int flagword_index = 0;
  uint16_t partial_flags;

  do {
    if (!amqp_decode_16(encoded, &offset, &partial_flags))
      return -ERROR_BAD_AMQP_DATA;
    flags |= (partial_flags << (flagword_index * 16));
    flagword_index++;
  } while (partial_flags & 1);

  switch (class_id) {"""
    for c in spec.allClasses(): genDecodeProperties(c)
    print """    default: return -ERROR_UNKNOWN_CLASS;
  }
}"""

    print """
int amqp_encode_method(amqp_method_number_t methodNumber,
                       void *decoded,
                       amqp_bytes_t encoded)
{
  size_t offset = 0;
  uint8_t bit_buffer;

  switch (methodNumber) {"""
    for m in methods: genEncodeMethodFields(m)
    print """    default: return -ERROR_UNKNOWN_METHOD;
  }
}"""

    print """
int amqp_encode_properties(uint16_t class_id,
                           void *decoded,
                           amqp_bytes_t encoded)
{
  size_t offset = 0;

  /* Cheat, and get the flags out generically, relying on the
     similarity of structure between classes */
  amqp_flags_t flags = * (amqp_flags_t *) decoded; /* cheating! */

  {
    /* We take a copy of flags to avoid destroying it, as it is used
       in the autogenerated code below. */
    amqp_flags_t remaining_flags = flags;
    do {
      amqp_flags_t remainder = remaining_flags >> 16;
      uint16_t partial_flags = remaining_flags & 0xFFFE;
      if (remainder != 0) { partial_flags |= 1; }
      if (!amqp_encode_16(encoded, &offset, partial_flags))
        return -ERROR_BAD_AMQP_DATA;
      remaining_flags = remainder;
    } while (remaining_flags != 0);
  }

  switch (class_id) {"""
    for c in spec.allClasses(): genEncodeProperties(c)
    print """    default: return -ERROR_UNKNOWN_CLASS;
  }
}"""

    for m in methods:
        if not m.isSynchronous:
            continue

        info = apiMethodInfo.get(m.fullName(), [])
        if info is False:
            continue

        print
        print m.apiPrototype()
        print "{"
        print "  %s req;" % (m.structName(),)

        for f in m.arguments:
            n = c_ize(f.name)

            val = apiMethodsSuppressArgs.get(n)
            if val is None and n in info:
                val = f.defaultvalue

            if val is None:
                val = n
            else:
                val = typeFor(spec, f).literal(val)


            print "  req.%s = %s;" % (n, val)

        reply = cConstantName(c_ize(m.klass.name) + '_' + c_ize(m.name)
                              + "_ok_method")
        print """
  return amqp_simple_rpc_decoded(state, channel, %s, %s, &req);
}
""" % (m.defName(), reply)

def genHrl(spec):
    def fieldDeclList(fields):
        if fields:
            return ''.join(["  %s %s;\n" % (typeFor(spec, f).ctype,
                                            c_ize(f.name))
                            for f in fields])
        else:
            return "  char dummy; /* Dummy field to avoid empty struct */\n"

    def propDeclList(fields):
        return ''.join(["  %s %s;\n" % (typeFor(spec, f).ctype, c_ize(f.name))
                        for f in fields
                        if spec.resolveDomain(f.domain) != 'bit'])

    methods = spec.allMethods()

    print """/* Autogenerated code. Do not edit. */
#ifndef librabbitmq_amqp_framing_h
#define librabbitmq_amqp_framing_h

#ifdef __cplusplus
extern "C" {
#endif
"""
    print "#define AMQP_PROTOCOL_VERSION_MAJOR %d" % (spec.major)
    print "#define AMQP_PROTOCOL_VERSION_MINOR %d" % (spec.minor)
    print "#define AMQP_PROTOCOL_VERSION_REVISION %d" % (spec.revision)
    print "#define AMQP_PROTOCOL_PORT %d" % (spec.port)

    for (c,v,cls) in spec.constants:
        print "#define %s %s" % (cConstantName(c), v)
    print

    print """/* Function prototypes. */

extern char const *amqp_constant_name(int constantNumber);
extern amqp_boolean_t amqp_constant_is_hard_error(int constantNumber);
RABBITMQ_EXPORT char const *amqp_method_name(amqp_method_number_t methodNumber);
extern amqp_boolean_t amqp_method_has_content(amqp_method_number_t methodNumber);
extern int amqp_decode_method(amqp_method_number_t methodNumber,
                              amqp_pool_t *pool,
                              amqp_bytes_t encoded,
                              void **decoded);
extern int amqp_decode_properties(uint16_t class_id,
                                  amqp_pool_t *pool,
                                  amqp_bytes_t encoded,
                                  void **decoded);
extern int amqp_encode_method(amqp_method_number_t methodNumber,
                              void *decoded,
                              amqp_bytes_t encoded);
extern int amqp_encode_properties(uint16_t class_id,
                                  void *decoded,
                                  amqp_bytes_t encoded);
"""

    print "/* Method field records. */\n"
    for m in methods:
        methodid = m.klass.index << 16 | m.index
        print "#define %s ((amqp_method_number_t) 0x%.08X) /* %d, %d; %d */" % \
              (m.defName(),
               methodid,
               m.klass.index,
               m.index,
               methodid)
        print "typedef struct %s_ {\n%s} %s;\n" % \
              (m.structName(), fieldDeclList(m.arguments), m.structName())

    print "/* Class property records. */"
    for c in spec.allClasses():
        print "#define %s (0x%.04X) /* %d */" % \
              (cConstantName(c.name + "_class"), c.index, c.index)
        index = 0
        for f in c.fields:
            if index % 16 == 15:
                index = index + 1
            shortnum = index / 16
            partialindex = 15 - (index % 16)
            bitindex = shortnum * 16 + partialindex
            print '#define %s (1 << %d)' % (cFlagName(c, f), bitindex)
            index = index + 1
        print "typedef struct %s_ {\n  amqp_flags_t _flags;\n%s} %s;\n" % \
              (c.structName(),
               fieldDeclList(c.fields),
               c.structName())

    print "/* API functions for methods */\n"

    for m in methods:
        if m.isSynchronous and apiMethodInfo.get(m.fullName()) is not False:
            print "RABBITMQ_EXPORT %s;" % (m.apiPrototype(),)

    print """
#ifdef __cplusplus
}
#endif

#endif"""

def generateErl(specPath):
    genErl(AmqpSpec(specPath))

def generateHrl(specPath):
    genHrl(AmqpSpec(specPath))

if __name__ == "__main__":
    do_main(generateHrl, generateErl)
