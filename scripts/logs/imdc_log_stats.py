#!/usr/bin/env python3
import sys, json, re, collections, datetime

def parse_line(line):
    try:
        obj = json.loads(line)
        return {
            "time": obj.get("time_local"),
            "ip": obj.get("remote_addr"),
            "method": obj.get("request_method"),
            "status": int(obj.get("status", 0)),
            "uri": obj.get("uri"),
            "trace": obj.get("trace_id"),
        }
    except Exception:
        return None

def minute_bucket(tstr):
    # Nginx time_local format: 10/Sep/2025:13:45:22 +0000
    try:
        dt = datetime.datetime.strptime(tstr.split(" ")[0], "%d/%b/%Y:%H:%M:%S")
    except Exception:
        return "unknown"
    return dt.strftime("%Y-%m-%d %H:%M")

def main():
    path = sys.argv[1] if len(sys.argv) > 1 else "/var/log/nginx/imdc_access.log"
    status_counts = collections.Counter()
    ip_counts = collections.Counter()
    uri_counts = collections.Counter()
    per_minute = collections.Counter()
    total = 0

    with open(path, "r", encoding="utf-8", errors="ignore") as f:
        for line in f:
            line = line.strip()
            if not line or line[0] != "{": 
                continue
            rec = parse_line(line)
            if not rec: 
                continue
            total += 1
            status_counts[rec["status"]] += 1
            if rec["ip"]:
                ip_counts[rec["ip"]] += 1
            if rec["uri"]:
                uri_counts[rec["uri"]] += 1
            if rec["time"]:
                per_minute[minute_bucket(rec["time"])] += 1

    print("== IMDC Log Stats ==")
    print(f"File: {path}")
    print(f"Total lines: {total}\n")

    print("-- Status Codes (top) --")
    for code, cnt in status_counts.most_common(20):
        print(f"{code}: {cnt}")
    print()

    print("-- Top IPs --")
    for ip, cnt in ip_counts.most_common(10):
        print(f"{ip}: {cnt}")
    print()

    print("-- Top URIs --")
    for uri, cnt in uri_counts.most_common(15):
        print(f"{cnt:6}  {uri}")
    print()

    print("-- Requests per minute (last 20 buckets) --")
    for bucket, cnt in sorted(per_minute.items())[-20:]:
        print(f"{bucket}: {cnt}")

if __name__ == "__main__":
    main()
