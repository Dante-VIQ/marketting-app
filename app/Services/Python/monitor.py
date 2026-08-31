#!/usr/bin/env python3
# app/Python/monitor.py

import sys
import json
import os
import httpx
from datetime import datetime, timedelta

def main():
    # Get parameters
    params = json.loads(sys.argv[1] if len(sys.argv) > 1 else '{}')
    brand_id = params.get('brand_id')

    # Laravel API config
    api_url = os.getenv('LARAVEL_API_URL', 'http://localhost:8000/api')
    api_key = os.getenv('LARAVEL_API_KEY', '')

    opportunities = []

    try:
        with httpx.Client(timeout=30) as client:
            # Get analytics data
            analytics = client.get(
                f"{api_url}/analytics/{brand_id}",
                headers={'X-API-Key': api_key}
            ).json()

            # Get SEO issues
            seo = client.get(
                f"{api_url}/seo/issues/{brand_id}",
                headers={'X-API-Key': api_key}
            ).json()

            # Get leads needing follow-up
            leads = client.get(
                f"{api_url}/leads/pending/{brand_id}",
                headers={'X-API-Key': api_key}
            ).json()

    except Exception as e:
        print(json.dumps({'error': str(e)}))
        return

    # 1. Check for SEO issues
    for issue in seo.get('issues', []):
        if issue.get('severity') in ['high', 'critical']:
            opportunities.append({
                'type': 'seo_issue',
                'title': f"SEO Issue: {issue['type']}",
                'description': issue['description'],
                'target_url': issue['page_url'],
                'severity': issue['severity'],
                'impact': 500 if issue['severity'] == 'high' else 1000,
                'requires_approval': False,
                'metadata': {'issue_id': issue['id']},
            })

    # 2. Check for lead opportunities
    for lead in leads.get('leads', []):
        if lead.get('score') == 'hot':
            opportunities.append({
                'type': 'lead_opportunity',
                'title': f"Hot Lead: {lead.get('full_name', 'Unknown')}",
                'description': f"Lead {lead.get('full_name', '')} has been waiting {lead.get('days_waiting', 0)} days",
                'target_url': None,
                'severity': 'high',
                'impact': lead.get('estimated_value', 1000),
                'requires_approval': True,
                'metadata': {'lead_id': lead['id']},
            })

    # 3. Check for content gaps (if blog posts < 4 per month)
    blog_count = analytics.get('blog_posts_this_month', 0)
    if blog_count < 4:
        opportunities.append({
            'type': 'content_gap',
            'title': 'Low Blog Output',
            'description': f'Only {blog_count} blog posts this month. Target: 4+ per month.',
            'target_url': None,
            'severity': 'medium',
            'impact': 300,
            'requires_approval': False,
            'metadata': {'blog_count': blog_count},
        })

    print(json.dumps({'opportunities': opportunities}))

if __name__ == '__main__':
    main()
