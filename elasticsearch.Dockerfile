FROM elasticsearch:7.17.18
USER root
RUN chmod 777 /tmp
RUN chmod 777 logs
RUN chmod -R 777 /usr/share/elasticsearch
RUN /usr/share/elasticsearch/bin/elasticsearch-plugin install analysis-icu
USER elasticsearch