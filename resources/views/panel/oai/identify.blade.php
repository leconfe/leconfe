<?xml version="1.0" encoding="UTF-8"?>
<?xml-stylesheet type="text/xsl" href="{{ $xsl }}" ?>
<OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/"
         xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/ http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd">
    <responseDate>{{ now()->toIso8601String() }}</responseDate>
    <request verb="Identify">{{ $baseURL }}</request>
    <Identify>
        <repositoryName>{{ $repositoryName }}</repositoryName>
        <baseURL>{{ $baseURL }}</baseURL>
        <protocolVersion>{{ $protocolVersion }}</protocolVersion>
        <adminEmail>{{ $adminEmail }}</adminEmail>
        <earliestDatestamp>{{ $earliestDatestamp }}</earliestDatestamp>
        <deletedRecord>{{ $deletedRecord }}</deletedRecord>
        <granularity>{{ $granularity }}</granularity>
        <compression>gzip</compression>
        <description>
            <oai-identifier
                xmlns="http://www.openarchives.org/OAI/2.0/oai-identifier"
                xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/oai-identifier
					http://www.openarchives.org/OAI/2.0/oai-identifier.xsd">
                <scheme>oai</scheme>
                <repositoryIdentifier>ojs.ejournal.upi.edu</repositoryIdentifier>
                <delimiter>:</delimiter>
                <sampleIdentifier>oai:ojs.ejournal.upi.edu:article/1</sampleIdentifier>
            </oai-identifier>
        </description>
        <description>
            <toolkit
                xmlns="http://oai.dlib.vt.edu/OAI/metadata/toolkit"
                xsi:schemaLocation="http://oai.dlib.vt.edu/OAI/metadata/toolkit
					http://oai.dlib.vt.edu/OAI/metadata/toolkit.xsd">
                <title>Open Journal Systems</title>
                <author>
                    <name>Public Knowledge Project</name>
                    <email>pkp.contact@gmail.com</email>
                </author>
                <version>2.4.8.5</version>
                <URL>http://pkp.sfu.ca/ojs/</URL>
            </toolkit>
        </description>
    </Identify>
</OAI-PMH>
