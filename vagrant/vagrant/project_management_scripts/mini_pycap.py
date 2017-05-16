#!/usr/bin/env python
import pycurl, cStringIO

def send(redcapurl, data):
    try:
        buf = cStringIO.StringIO()

        ch = pycurl.Curl()
        ch.setopt(ch.URL, redcapurl)
        ch.setopt(ch.HTTPPOST, data.items())
        ch.setopt(ch.WRITEFUNCTION, buf.write)
        ch.setopt(pycurl.SSL_VERIFYPEER, 0)
        ch.setopt(pycurl.SSL_VERIFYHOST, 0)
        ch.perform()
        ch.close()

        return buf.getvalue()
    except:
        return None
