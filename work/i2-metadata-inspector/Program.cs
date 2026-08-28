using System.Reflection.Metadata;
using System.Reflection.Metadata.Ecma335;
using System.Reflection.PortableExecutable;
using System.Reflection.Emit;

if (args.Length is < 1 or > 2) throw new ArgumentException("Usage: i2-metadata-inspector <assembly.dll> [exact-type-name[::method-name]|--types]");
var selector = args.Length == 2 ? args[1] : null;
var selectorParts = selector?.Split(new[] { "::" }, StringSplitOptions.None) ?? Array.Empty<string>();
var exactTypeName = selectorParts.Length > 0 ? selectorParts[0] : null;
var exactMethodName = selectorParts.Length == 2 ? selectorParts[1] : null;

using var stream = File.OpenRead(args[0]);
using var pe = new PEReader(stream);
var reader = pe.GetMetadataReader();

string FullName(TypeDefinition type)
{
    var ns = reader.GetString(type.Namespace);
    var name = reader.GetString(type.Name);
    return string.IsNullOrEmpty(ns) ? name : $"{ns}.{name}";
}

string AttributeSummary(CustomAttributeHandle handle)
{
    var attribute = reader.GetCustomAttribute(handle);
    var blob = reader.GetBlobBytes(attribute.Value);
    var printable = string.Concat(blob.Select(b => b is >= 32 and <= 126 ? (char)b : '.'));
    return $"ctor=0x{MetadataTokens.GetToken(attribute.Constructor):X8} blob={Convert.ToHexString(blob)} text={printable}";
}

var opcodes = typeof(OpCodes).GetFields()
    .Where(field => field.FieldType == typeof(OpCode))
    .Select(field => (OpCode)field.GetValue(null)!)
    .ToDictionary(opcode => (ushort)opcode.Value);

string MetadataOperand(int token)
{
    try
    {
        if ((token & unchecked((int)0xFF000000)) == 0x70000000)
            return $"\"{reader.GetUserString(MetadataTokens.UserStringHandle(token))}\"";

        var handle = MetadataTokens.EntityHandle(token);
        return handle.Kind switch
        {
            HandleKind.MethodDefinition => reader.GetString(reader.GetMethodDefinition((MethodDefinitionHandle)handle).Name),
            HandleKind.MemberReference => reader.GetString(reader.GetMemberReference((MemberReferenceHandle)handle).Name),
            HandleKind.FieldDefinition => reader.GetString(reader.GetFieldDefinition((FieldDefinitionHandle)handle).Name),
            HandleKind.TypeDefinition => FullName(reader.GetTypeDefinition((TypeDefinitionHandle)handle)),
            HandleKind.TypeReference => reader.GetString(reader.GetTypeReference((TypeReferenceHandle)handle).Name),
            _ => $"token=0x{token:X8}",
        };
    }
    catch
    {
        return $"token=0x{token:X8}";
    }
}

void DumpIl(MethodDefinition method)
{
    if (method.RelativeVirtualAddress == 0) return;
    var bytes = pe.GetMethodBody(method.RelativeVirtualAddress).GetILBytes();
    for (var offset = 0; offset < bytes.Length;)
    {
        var instructionOffset = offset;
        var raw = bytes[offset++];
        ushort value = raw == 0xFE ? (ushort)(0xFE00 | bytes[offset++]) : raw;
        var opcode = opcodes[value];
        var size = opcode.OperandType switch
        {
            OperandType.InlineNone => 0,
            OperandType.ShortInlineBrTarget or OperandType.ShortInlineI or OperandType.ShortInlineVar => 1,
            OperandType.InlineVar => 2,
            OperandType.InlineI or OperandType.InlineBrTarget or OperandType.InlineField or OperandType.InlineMethod or OperandType.InlineSig or OperandType.InlineString or OperandType.InlineTok or OperandType.InlineType or OperandType.ShortInlineR => 4,
            OperandType.InlineI8 or OperandType.InlineR => 8,
            OperandType.InlineSwitch => 4 + (BitConverter.ToInt32(bytes, offset) * 4),
            _ => throw new InvalidOperationException($"Unsupported operand type: {opcode.OperandType}"),
        };
        var operand = size == 4 && opcode.OperandType is OperandType.InlineField or OperandType.InlineMethod or OperandType.InlineString or OperandType.InlineTok or OperandType.InlineType
            ? MetadataOperand(BitConverter.ToInt32(bytes, offset))
            : string.Empty;
        Console.WriteLine($"    IL_{instructionOffset:X4}: {opcode.Name} {operand}".TrimEnd());
        offset += size;
    }
}

foreach (var handle in reader.TypeDefinitions)
{
    var type = reader.GetTypeDefinition(handle);
    var name = FullName(type);
    if (exactTypeName == "--types")
    {
        Console.WriteLine(name);
        continue;
    }
    if (selector == "--types")
    {
        Console.WriteLine(name);
        continue;
    }
    if (exactTypeName is not null ? !name.Equals(exactTypeName, StringComparison.Ordinal) : (!name.StartsWith("I2.Loc.", StringComparison.Ordinal) && !name.Contains("LocalizationManager", StringComparison.Ordinal))) continue;

    Console.WriteLine($"TYPE {name} token=0x{MetadataTokens.GetToken(handle):X8}");
    foreach (var attributeHandle in type.GetCustomAttributes())
        Console.WriteLine($"  ATTRIBUTE {AttributeSummary(attributeHandle)}");
    foreach (var methodHandle in type.GetMethods())
    {
        var method = reader.GetMethodDefinition(methodHandle);
        if (exactMethodName is not null && !reader.GetString(method.Name).Equals(exactMethodName, StringComparison.Ordinal)) continue;
        Console.WriteLine($"  METHOD {reader.GetString(method.Name)} token=0x{MetadataTokens.GetToken(methodHandle):X8} flags={method.Attributes}");
        foreach (var attributeHandle in method.GetCustomAttributes())
            Console.WriteLine($"    ATTRIBUTE {AttributeSummary(attributeHandle)}");
        if (exactMethodName is not null) DumpIl(method);
    }
    foreach (var propertyHandle in type.GetProperties())
    {
        var property = reader.GetPropertyDefinition(propertyHandle);
        Console.WriteLine($"  PROPERTY {reader.GetString(property.Name)} token=0x{MetadataTokens.GetToken(propertyHandle):X8}");
    }
    foreach (var fieldHandle in type.GetFields())
    {
        var field = reader.GetFieldDefinition(fieldHandle);
        Console.WriteLine($"  FIELD {reader.GetString(field.Name)} token=0x{MetadataTokens.GetToken(fieldHandle):X8} flags={field.Attributes}");
    }
}
