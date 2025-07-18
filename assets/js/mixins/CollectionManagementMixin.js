// CollectionManagementMixin.js

import axios from 'axios';

export default {
  methods: {
    collectionToggleAll() {
      const allChecked = this.data.data.every(row => this.collectionArray.includes(row.id));
      if (allChecked) {
        this.clearCollection();
      } else {
        for (const row of this.data.data) {
          if (!this.collectionArray.includes(row.id)) {
            this.collectionArray.push(row.id);
          }
        }
      }
    },

    clearCollection() {
      this.collectionArray = [];
    },

    addManagementsToSelection(managementCollections) {
      this._updateManagements('add', 'selection', managementCollections);
    },

    removeManagementsFromSelection(managementCollections) {
      this._updateManagements('remove', 'selection', managementCollections);
    },

    addManagementsToResults(managementCollections) {
      this._updateManagements('add', 'results', managementCollections);
    },

    removeManagementsFromResults(managementCollections) {
      this._updateManagements('remove', 'results', managementCollections);
    },

    _updateManagements(action, target, managementCollections) {
      const url = this.urls?.[`managements_${action}`];
      const payload = target === 'selection'
        ? { ids: this.collectionArray, managements: managementCollections }
        : { filter: this.constructFilterValues(), managements: managementCollections };

      const messages = {
        add: {
          success: 'Management collections added successfully.',
          error: 'Something went wrong while adding the management collections.',
        },
        remove: {
          success: 'Management collections removed successfully.',
          error: 'Something went wrong while removing the management collections.',
        },
      };

      this.openRequests += 1;

      axios
        .put(url, payload)
        .then(() => {
          this.noHistory = true;
          this.$refs.resultTable?.refresh();
          this.alerts.push({
            type: 'success',
            message: messages[action].success,
          });
        })
        .catch((error) => {
          this.alerts.push({
            type: 'error',
            message: messages[action].error,
          });
          console.error(error);
        })
        .finally(() => {
          this.openRequests -= 1;
        });
    },
  },
};
