<?xml version="1.0" encoding="UTF-8"?>
<?xml-stylesheet type="text/xsl" href="{{ $xsl }}" ?>
<OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/"
         xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/ http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd">
    <responseDate>{{ now()->toIso8601String() }}</responseDate>
    <request verb="ListMetadataFormats">{{ $baseURL }}</request>
    <ListMetadataFormats>
        @foreach($metadataFormatArr as $mf)
            <metadataFormat>
                <metadataPrefix>{{ $mf['metadataPrefix'] }}</metadataPrefix>
                <schema>{{ $mf['schema'] }}</schema>
                <metadataNamespace>{{ $mf['metadataNamespace'] }}</metadataNamespace>
            </metadataFormat>
        @endforeach
    </ListMetadataFormats>
</OAI-PMH>
