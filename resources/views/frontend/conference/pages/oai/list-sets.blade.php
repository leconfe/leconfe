<?xml version="1.0" encoding="UTF-8"?>
<?xml-stylesheet type="text/xsl" href="{{ $xsl }}" ?>
<OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/"
         xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/ http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd">
    <responseDate>{{ now()->toIso8601String() }}</responseDate>
    <request verb="ListSets">{{ $baseURL }}</request>
    <ListSets>
{{--        @foreach ($sets as $set)--}}
{{--            <setSpec>{{ $set->spec }}</setSpec>--}}
{{--            <setName>{{ $set->name }}</setName>--}}
{{--            <setDescription>--}}
{{--                <oai_dc:dc--}}
{{--                    xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/"--}}
{{--                    xmlns:dc="http://purl.org/dc/elements/1.1/"--}}
{{--                    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"--}}
{{--                    xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/oai_dc/--}}
{{--						http://www.openarchives.org/OAI/2.0/oai_dc.xsd">--}}
{{--                    <dc:description></dc:description>--}}
{{--                </oai_dc:dc>--}}
{{--            </setDescription>--}}
{{--        @endforeach--}}
    </ListSets>
</OAI-PMH>
